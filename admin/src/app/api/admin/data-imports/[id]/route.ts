import { NextResponse } from "next/server";
import type { NextRequest } from "next/server";
import { adminApiFetchRaw } from "@/lib/adminApi";

type Ctx = { params: Promise<{ id: string }> };

export async function GET(request: NextRequest, ctx: Ctx) {
  const { id } = await ctx.params;
  const tableId = request.nextUrl.searchParams.get("data_table_id");
  const result = await adminApiFetchRaw(`/admin/data-imports/${id}${tableId ? `?data_table_id=${tableId}` : ""}`);
  return NextResponse.json(result.json ?? {}, { status: result.status });
}
