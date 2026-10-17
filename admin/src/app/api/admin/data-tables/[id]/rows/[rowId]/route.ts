import { NextResponse } from "next/server";
import type { NextRequest } from "next/server";
import { adminApiFetchRaw } from "@/lib/adminApi";

type Ctx = { params: Promise<{ id: string; rowId: string }> };

export async function PATCH(request: NextRequest, ctx: Ctx) {
  const { id, rowId } = await ctx.params;
  const body = await request.text();
  const result = await adminApiFetchRaw(`/admin/data-tables/${id}/rows/${rowId}`, { method: "PATCH", body });
  return NextResponse.json(result.json ?? {}, { status: result.status });
}

export async function DELETE(_request: NextRequest, ctx: Ctx) {
  const { id, rowId } = await ctx.params;
  const result = await adminApiFetchRaw(`/admin/data-tables/${id}/rows/${rowId}`, { method: "DELETE" });
  return NextResponse.json({}, { status: result.status === 204 ? 200 : result.status });
}
