import { NextResponse } from "next/server";
import type { NextRequest } from "next/server";
import { errorResponse, requestApi } from "@/lib/apiTransport";
import { bffResponse } from "@/lib/bff";
import { adminApiFetch, getAdminToken } from "@/lib/adminApi";

// Multipart, like /api/admin/uploads: the FormData goes to Laravel as-is
// (adminApiFetch would force Content-Type: application/json).
export async function POST(request: NextRequest) {
  const token = await getAdminToken();
  if (!token) {
    return NextResponse.json({ message: "Unauthenticated.", code: "unauthenticated", status: 401 }, { status: 401 });
  }

  const answer = await requestApi("/admin/settings/logo", {
    method: "POST",
    headers: { Authorization: `Bearer ${token}` },
    body: await request.formData(),
  });

  return answer.ok ? NextResponse.json(answer.json ?? {}, { status: answer.status }) : errorResponse(answer);
}

export async function DELETE() {
  const result = await adminApiFetch("/admin/settings/logo", { method: "DELETE" });
  return bffResponse(result);
}
