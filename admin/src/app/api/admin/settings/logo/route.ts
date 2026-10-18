import { NextResponse } from "next/server";
import type { NextRequest } from "next/server";
import { adminApiFetch, getAdminToken } from "@/lib/adminApi";

const API_BASE = process.env.NEXT_INTERNAL_API_URL ?? process.env.NEXT_PUBLIC_API_URL ?? "http://localhost:8080/api/v1";

// Multipart, like /api/admin/uploads: the FormData goes to Laravel as-is
// (adminApiFetch would force Content-Type: application/json).
export async function POST(request: NextRequest) {
  const token = await getAdminToken();
  if (!token) {
    return NextResponse.json({ message: "Unauthenticated." }, { status: 401 });
  }

  const response = await fetch(`${API_BASE}/admin/settings/logo`, {
    method: "POST",
    headers: { Authorization: `Bearer ${token}`, Accept: "application/json" },
    body: await request.formData(),
  });

  const json = await response.json().catch(() => null);
  return NextResponse.json(json, { status: response.status });
}

export async function DELETE() {
  const result = await adminApiFetch("/admin/settings/logo", { method: "DELETE" });
  return NextResponse.json({ data: result.data }, { status: result.ok ? 200 : result.status });
}
