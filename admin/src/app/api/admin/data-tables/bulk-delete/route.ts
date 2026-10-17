import { NextResponse } from "next/server";
import type { NextRequest } from "next/server";
import { adminApiFetchRaw } from "@/lib/adminApi";

export async function POST(request: NextRequest) {
  const body = await request.text();
  const result = await adminApiFetchRaw("/admin/data-tables/bulk-delete", { method: "POST", body });
  return NextResponse.json(result.json ?? {}, { status: result.status === 204 ? 200 : result.status });
}
