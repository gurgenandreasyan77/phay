// Server-only: private files (AI chat attachments, brand pictures) pass
// through the admin's server with the person's token. The browser never
// gets an API or storage URL, only /api/admin/...; the API's own headers
// (type, inline, nosniff, sandbox) are kept.
import { NextResponse } from "next/server";
import type { NextRequest } from "next/server";
import { API_BASE, errorResponse, requestApi } from "./apiTransport";
import { getAdminToken } from "./adminApi";

const PASSED_HEADERS = [
  "content-type",
  "content-length",
  "content-disposition",
  "cache-control",
  "x-content-type-options",
  "content-security-policy",
];

function unauthenticated() {
  return NextResponse.json({ message: "Unauthenticated.", code: "unauthenticated", status: 401 }, { status: 401 });
}

/** GET of a file: streamed as the API answers it. */
export async function proxyApiFile(path: string): Promise<Response> {
  const token = await getAdminToken();
  if (!token) return unauthenticated();

  let response: Response;
  try {
    response = await fetch(`${API_BASE}${path}`, {
      headers: { Authorization: `Bearer ${token}`, Accept: "*/*" },
      cache: "no-store",
    });
  } catch {
    return NextResponse.json({ message: "The API could not be reached.", code: "api_unreachable", status: 503 }, { status: 503 });
  }

  if (!response.ok || !response.body) {
    return new NextResponse(null, { status: response.ok ? 502 : response.status });
  }

  const headers = new Headers();
  for (const name of PASSED_HEADERS) {
    const value = response.headers.get(name);
    if (value) headers.set(name, value);
  }
  return new Response(response.body, { status: 200, headers });
}

/** A multipart upload passed on as it is (fetch sets the boundary). */
export async function proxyApiUpload(path: string, request: NextRequest): Promise<NextResponse> {
  const token = await getAdminToken();
  if (!token) return unauthenticated();

  const answer = await requestApi(path, {
    method: "POST",
    headers: { Authorization: `Bearer ${token}` },
    body: await request.formData(),
  });

  return answer.ok ? NextResponse.json(answer.json ?? {}, { status: answer.status }) : errorResponse(answer);
}
