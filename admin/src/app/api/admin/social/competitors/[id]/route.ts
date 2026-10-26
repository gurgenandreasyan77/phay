import { NextResponse } from "next/server";
import type { NextRequest } from "next/server";
import { adminApiFetchRaw } from "@/lib/adminApi";

type Context = { params: Promise<{ id: string }> };

/** One watched account with its latest posts. */
export async function GET(_request: NextRequest, ctx: Context) {
  const { id } = await ctx.params;
  const result = await adminApiFetchRaw(`/admin/social/competitors/${encodeURIComponent(id)}`);
  return NextResponse.json(result.json ?? {}, { status: result.status });
}

export async function DELETE(_request: NextRequest, ctx: Context) {
  const { id } = await ctx.params;
  const result = await adminApiFetchRaw(`/admin/social/competitors/${encodeURIComponent(id)}`, { method: "DELETE" });
  return result.status === 204 ? new NextResponse(null, { status: 204 }) : NextResponse.json(result.json ?? {}, { status: result.status });
}
