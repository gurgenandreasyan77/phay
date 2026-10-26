// API errors, shared by server and client code (no server-only imports
// here). The API answers every error as
//   { message, code, status, request_id, errors?, retry_after?, debug? }
// (debug only with APP_DEBUG, see api/app/Exceptions/ApiExceptionRenderer.php);
// the admin's server passes that body on to the browser as it is.

export type ApiErrorDebug = {
  exception?: string;
  message?: string;
  file?: string;
  line?: number;
  trace?: Array<{ file?: string; line?: number; function?: string; class?: string }>;
};

export type ApiErrorBody = {
  message?: string;
  code?: string;
  status?: number;
  request_id?: string;
  errors?: Record<string, string[]>;
  retry_after?: number;
  debug?: ApiErrorDebug;
};

export type ApiErrorInfo = {
  status: number;
  code: string;
  /** The API's own message (English, for developers and logs). */
  message: string;
  requestId: string | null;
  errors?: Record<string, string[]>;
  retryAfter?: number;
  debug?: ApiErrorDebug;
};

/** Codes the admin's server adds itself, when the API can't be reached. */
export const API_UNREACHABLE = "api_unreachable";

const CODE_BY_STATUS: Record<number, string> = {
  400: "bad_request",
  401: "unauthenticated",
  402: "payment_required",
  403: "forbidden",
  404: "not_found",
  405: "method_not_allowed",
  409: "conflict",
  413: "payload_too_large",
  419: "session_expired",
  422: "validation_failed",
  429: "too_many_requests",
  500: "server_error",
  502: "bad_gateway",
  503: "service_unavailable",
  504: "gateway_timeout",
};

export function codeForStatus(status: number): string {
  return CODE_BY_STATUS[status] ?? (status >= 500 ? "server_error" : "error");
}

/** Reads an error answer, whatever shape it has (an old API, an HTML page from nginx...). */
export function parseApiError(status: number, json: unknown, requestId?: string | null): ApiErrorInfo {
  const body = json && typeof json === "object" ? (json as ApiErrorBody) : {};

  return {
    status,
    code: typeof body.code === "string" && body.code ? body.code : codeForStatus(status),
    message: typeof body.message === "string" && body.message ? body.message : `HTTP ${status}`,
    requestId: body.request_id ?? requestId ?? null,
    ...(body.errors ? { errors: body.errors } : {}),
    ...(typeof body.retry_after === "number" ? { retryAfter: body.retry_after } : {}),
    ...(body.debug ? { debug: body.debug } : {}),
  };
}

/** Reads a failed fetch() answer of the admin's own /api routes in the browser. */
export async function readApiError(response: Response): Promise<ApiErrorInfo> {
  const json = await response.json().catch(() => null);
  return parseApiError(response.status, json, response.headers.get("x-request-id"));
}

const FRIENDLY: Record<string, string> = {
  unauthenticated: "Ձեր մուտքի ժամկետն ավարտվել է։ Խնդրում ենք նորից մուտք գործել։",
  session_expired: "Ձեր մուտքի ժամկետն ավարտվել է։ Թարմացրեք էջը։",
  forbidden: "Ձեր դերը թույլ չի տալիս կատարել այս գործողությունը։",
  two_factor_setup_required: "Նախ միացրեք երկքայլ նույնականացումը (2FA)։",
  payment_required: "Բիզնեսի բաժանորդագրությունն ավարտվել է։",
  subscription_expired: "Բիզնեսի բաժանորդագրությունն ավարտվել է։",
  not_found: "Այն, ինչ փնտրում եք, չի գտնվել կամ արդեն ջնջվել է։",
  method_not_allowed: "Այս գործողությունը հասանելի չէ։",
  conflict: "Տվյալներն արդեն փոխվել են։ Թարմացրեք էջը և փորձեք նորից։",
  payload_too_large: "Ֆայլը չափազանց մեծ է։",
  too_many_requests: "Չափազանց շատ փորձեր։ Մի փոքր սպասեք և փորձեք նորից։",
  server_error: "Սերվերում սխալ տեղի ունեցավ։ Փորձեք մի փոքր ուշ։",
  bad_gateway: "Սերվերը ժամանակավորապես անհասանելի է։ Փորձեք մի փոքր ուշ։",
  service_unavailable: "Սերվերը ժամանակավորապես անհասանելի է։ Փորձեք մի փոքր ուշ։",
  gateway_timeout: "Սերվերը չպատասխանեց ժամանակին։ Փորձեք նորից։",
  maintenance: "Phay-ում տեխնիկական աշխատանքներ են։ Փորձեք մի քանի րոպեից։",
  [API_UNREACHABLE]: "Սերվերի հետ կապ չկա։ Ստուգեք ինտերնետը կամ փորձեք մի փոքր ուշ։",
};

/**
 * What to show a person: the first validation message of a 422 (the API
 * writes those for people, mostly in Armenian), otherwise an Armenian text
 * for the code, with the request id so support can find it in the logs.
 */
export function friendlyApiError(error: ApiErrorInfo, fallback = "Ինչ-որ բան այն չէ։ Փորձեք նորից։"): string {
  if (error.errors) {
    const first = Object.values(error.errors).flat()[0];
    if (first) return first;
  }

  const text = FRIENDLY[error.code] ?? FRIENDLY[codeForStatus(error.status)] ?? fallback;
  return error.requestId && error.status >= 500 ? `${text} (կոդ՝ ${shortId(error.requestId)})` : text;
}

/** The first 8 characters of a request id: enough to find it in the logs. */
export function shortId(id: string): string {
  return id.slice(0, 8);
}

/** One line for a developer's console: status, code, message, request id, where it broke. */
export function describeApiError(error: ApiErrorInfo, request?: string): string {
  const where = error.debug?.file ? ` at ${error.debug.file}:${error.debug.line ?? "?"}` : "";
  const cls = error.debug?.exception ? ` [${error.debug.exception}]` : "";
  const id = error.requestId ? ` (request ${error.requestId})` : "";
  return `${request ? `${request} → ` : ""}${error.status} ${error.code}: ${error.message}${cls}${where}${id}`;
}

/**
 * Thrown by server code when the API fails in a way a page can't work
 * around (shown by the nearest error.tsx; in production Next keeps only
 * the digest, so the message is for the developer).
 */
export class ApiRequestError extends Error {
  constructor(
    readonly info: ApiErrorInfo,
    request?: string,
  ) {
    super(describeApiError(info, request));
    this.name = "ApiRequestError";
  }
}
