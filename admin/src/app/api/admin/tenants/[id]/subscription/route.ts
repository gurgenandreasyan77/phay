import { NextResponse } from "next/server";
import type { NextRequest } from "next/server";
import { adminApiFetchRaw } from "@/lib/adminApi";

// Typed by hand: RouteContext only knows routes `next dev`/`next build` has
// already generated types for.
type Ctx = { params: Promise<{ id: string }> };

export async function GET(_request: NextRequest, ctx: Ctx) {
  const { id } = await ctx.params;
  const result = await adminApiFetchRaw(`/admin/tenants/${id}/subscription`);
  return NextResponse.json(result.json ?? {}, { status: result.status });
}

/** The plan or unlimited access. */
export async function PATCH(request: NextRequest, ctx: Ctx) {
  const { id } = await ctx.params;
  const body = await request.text();
  const result = await adminApiFetchRaw(`/admin/tenants/${id}/subscription`, { method: "PATCH", body });
  return NextResponse.json(result.json ?? {}, { status: result.status });
}
