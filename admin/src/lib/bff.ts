// Server-only: the answer of an admin route handler built on adminApiFetch.
import { NextResponse } from "next/server";
import type { AdminApiResult } from "./adminApi";

/**
 * Success: `{ data }` (or `{}` with `empty`) and the given status. Failure:
 * the API's own error body and status as they are (message, code, 422
 * errors, request id), so the screen and the developer see the real reason
 * instead of an empty answer.
 */
export function bffResponse(
  result: AdminApiResult<unknown>,
  options: { status?: number; empty?: boolean } = {},
): NextResponse {
  if (!result.ok) {
    return NextResponse.json(result.body ?? {}, {
      status: result.status,
      headers: result.requestId ? { "X-Request-Id": result.requestId } : undefined,
    });
  }

  return NextResponse.json(options.empty ? {} : { data: result.data }, { status: options.status ?? 200 });
}
