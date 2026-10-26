import { NextResponse } from "next/server";
import type { NextRequest } from "next/server";
import { errorResponse, requestApi } from "@/lib/apiTransport";
import { getAdminToken } from "@/lib/adminApi";

// Not built on adminApiFetch(): that helper always sends
// Content-Type: application/json, which would corrupt this endpoint's
// multipart/form-data body (the boundary fetch sets automatically must be
// left alone). The incoming FormData is forwarded to the Laravel admin
// upload endpoint as-is, with only the bearer token added.
export async function POST(request: NextRequest) {
  const token = await getAdminToken();
  if (!token) {
    return NextResponse.json({ message: "Unauthenticated.", code: "unauthenticated", status: 401 }, { status: 401 });
  }

  const formData = await request.formData();

  const answer = await requestApi("/admin/uploads", {
    method: "POST",
    headers: { Authorization: `Bearer ${token}` },
    body: formData,
  });

  return answer.ok ? NextResponse.json(answer.json ?? {}, { status: answer.status }) : errorResponse(answer);
}
