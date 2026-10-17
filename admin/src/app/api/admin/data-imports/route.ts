import { NextResponse } from "next/server";
import type { NextRequest } from "next/server";
import { getAdminToken } from "@/lib/adminApi";

const API_BASE =
  process.env.NEXT_INTERNAL_API_URL ??
  process.env.NEXT_PUBLIC_API_URL ??
  "http://localhost:8080/api/v1";

// Multipart, so not adminApiFetch() (it forces a JSON content type); see
// ../uploads/route.ts. The file and data_table_id go to Laravel as they are.
export async function POST(request: NextRequest) {
  const token = await getAdminToken();
  if (!token) {
    return NextResponse.json({ message: "Unauthenticated." }, { status: 401 });
  }

  const response = await fetch(`${API_BASE}/admin/data-imports`, {
    method: "POST",
    headers: { Authorization: `Bearer ${token}`, Accept: "application/json" },
    body: await request.formData(),
  });

  const json = await response.json().catch(() => null);
  return NextResponse.json(json ?? {}, { status: response.status });
}
