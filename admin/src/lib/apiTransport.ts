// Server-only: the one place the admin's server talks to the Laravel API.
// Every call carries an X-Request-Id (the API logs and returns it), a
// failed call is logged with the API's real error (status, code, message,
// and in development the exception, file and line), and an API that can't
// be reached becomes a 503 api_unreachable answer instead of a crash.
import { NextResponse } from "next/server";
import { API_UNREACHABLE, describeApiError, parseApiError, type ApiErrorInfo } from "./apiError";

export const API_BASE =
  process.env.NEXT_INTERNAL_API_URL ?? process.env.NEXT_PUBLIC_API_URL ?? "http://localhost:8080/api/v1";

const IS_DEV = process.env.NODE_ENV !== "production";

export type ApiAnswer = {
  ok: boolean;
  status: number;
  /** The whole JSON body (null for 204 or a body that isn't JSON). */
  json: unknown;
  requestId: string;
  /** Set when the call failed. */
  error: ApiErrorInfo | null;
};

/**
 * Calls `path` of the API. Headers given in init win, so a multipart body
 * can leave Content-Type out (fetch then sets the boundary itself).
 */
export async function requestApi(path: string, init: RequestInit = {}): Promise<ApiAnswer> {
  const requestId = crypto.randomUUID();
  const method = (init.method ?? "GET").toUpperCase();
  const label = `${method} ${path}`;

  let response: Response;
  try {
    response = await fetch(`${API_BASE}${path}`, {
      ...init,
      headers: { Accept: "application/json", "X-Request-Id": requestId, ...init.headers },
      cache: "no-store",
    });
  } catch (cause) {
    const error: ApiErrorInfo = {
      status: 503,
      code: API_UNREACHABLE,
      message: `The API at ${API_BASE} could not be reached: ${cause instanceof Error ? cause.message : String(cause)}`,
      requestId,
    };
    console.error(`[api] ${describeApiError(error, label)}`);
    return { ok: false, status: 503, json: errorBody(error), requestId, error };
  }

  const json = response.status === 204 ? null : await response.json().catch(() => null);
  const answeredId = response.headers.get("x-request-id") ?? requestId;

  if (response.ok) {
    return { ok: true, status: response.status, json, requestId: answeredId, error: null };
  }

  const error = parseApiError(response.status, json, answeredId);
  log(error, label);

  // An answer that isn't the API's JSON (nginx's HTML 502, an empty body)
  // still reaches the browser in the usual shape.
  return { ok: false, status: response.status, json: json ?? errorBody(error), requestId: answeredId, error };
}

/** The API's own error body (or one in its shape), passed on to the browser as it is. */
export function errorResponse(answer: ApiAnswer): NextResponse {
  return NextResponse.json(answer.json ?? (answer.error ? errorBody(answer.error) : {}), {
    status: answer.status,
    headers: { "X-Request-Id": answer.requestId },
  });
}

function errorBody(error: ApiErrorInfo) {
  return { message: error.message, code: error.code, status: error.status, request_id: error.requestId };
}

// 5xx always (the container log is where production problems are looked
// up, by request id); 4xx only while developing, they are everyday answers
// (a wrong password, a validation error).
function log(error: ApiErrorInfo, label: string): void {
  if (error.status >= 500) {
    console.error(`[api] ${describeApiError(error, label)}`);
    if (IS_DEV && error.debug?.trace?.length) {
      console.error(
        error.debug.trace
          .slice(0, 8)
          .map((frame) => `    at ${frame.class ? `${frame.class}::` : ""}${frame.function ?? "?"} (${frame.file ?? "?"}:${frame.line ?? "?"})`)
          .join("\n"),
      );
    }
  } else if (IS_DEV) {
    console.warn(`[api] ${describeApiError(error, label)}`);
  }
}
