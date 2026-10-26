import { NextResponse } from "next/server";
import type { NextRequest } from "next/server";
import { errorResponse, requestApi } from "@/lib/apiTransport";
import { getAdminToken } from "@/lib/adminApi";

// Multipart, so not adminApiFetch() (it forces a JSON content type); see
// ../uploads/route.ts. The file and data_table_id go to Laravel as they are.
export async function POST(request: NextRequest) {
  const token = await getAdminToken();
  if (!token) {
    return NextResponse.json({ message: "Unauthenticated.", code: "unauthenticated", status: 401 }, { status: 401 });
  }

  const answer = await requestApi("/admin/data-imports", {
    method: "POST",
    headers: { Authorization: `Bearer ${token}` },
    body: await request.formData(),
  });

  return answer.ok ? NextResponse.json(answer.json ?? {}, { status: answer.status }) : errorResponse(answer);
}
