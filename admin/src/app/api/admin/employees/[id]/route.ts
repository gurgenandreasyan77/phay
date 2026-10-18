import { NextResponse } from "next/server";
import type { NextRequest } from "next/server";
import { adminApiFetchRaw } from "@/lib/adminApi";

type Context = { params: Promise<{ id: string }> };

export async function PATCH(request: NextRequest, ctx: Context) {
  const { id } = await ctx.params;
  const body = await request.text();
  const result = await adminApiFetchRaw(`/admin/employees/${id}`, { method: "PATCH", body });
  return NextResponse.json(result.json ?? {}, { status: result.status });
}

export async function DELETE(_request: NextRequest, ctx: Context) {
  const { id } = await ctx.params;
  const result = await adminApiFetchRaw(`/admin/employees/${id}`, { method: "DELETE" });
  return NextResponse.json(result.json ?? {}, { status: result.status === 204 ? 200 : result.status });
}
