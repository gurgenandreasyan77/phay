import { NextResponse } from "next/server";
import type { NextRequest } from "next/server";
import { adminApiFetch, adminApiFetchRaw } from "@/lib/adminApi";

type Context = { params: Promise<{ id: string }> };

export async function PATCH(request: NextRequest, ctx: Context) {
  const { id } = await ctx.params;
  const body = await request.text();
  const { status, json } = await adminApiFetchRaw(`/admin/posts/${id}`, { method: "PATCH", body });
  return NextResponse.json(json ?? {}, { status });
}

export async function DELETE(_request: NextRequest, ctx: Context) {
  const { id } = await ctx.params;
  const result = await adminApiFetch(`/admin/posts/${id}`, { method: "DELETE" });
  return NextResponse.json({}, { status: result.ok ? 200 : result.status });
}
