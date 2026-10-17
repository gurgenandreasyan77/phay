import { NextResponse } from "next/server";
import type { NextRequest } from "next/server";
import { getAdminToken } from "@/lib/adminApi";

const API_BASE =
  process.env.NEXT_INTERNAL_API_URL ??
  process.env.NEXT_PUBLIC_API_URL ??
  "http://localhost:8080/api/v1";

// Not built on adminApiFetch(): that helper always sends
// Content-Type: application/json, which would corrupt this endpoint's
// multipart/form-data body (the boundary fetch sets automatically must be
// left alone). The incoming FormData is forwarded to the Laravel admin
// upload endpoint as-is, with only the bearer token added.
export async function POST(request: NextRequest) {
  const token = await getAdminToken();
  if (!token) {
    return NextResponse.json({ message: "Unauthenticated." }, { status: 401 });
  }

  const formData = await request.formData();

  const response = await fetch(`${API_BASE}/admin/uploads`, {
    method: "POST",
    headers: { Authorization: `Bearer ${token}` },
    body: formData,
  });

  const json = await response.json().catch(() => null);
  return NextResponse.json(json, { status: response.status });
}
