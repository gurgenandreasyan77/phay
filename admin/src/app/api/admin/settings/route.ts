import { NextResponse } from "next/server";
import type { NextRequest } from "next/server";
import { adminApiFetch } from "@/lib/adminApi";

export async function PUT(request: NextRequest) {
  const body = await request.text();
  const result = await adminApiFetch("/admin/settings", { method: "PUT", body });
  return NextResponse.json({ data: result.data }, { status: result.ok ? 200 : result.status });
}
