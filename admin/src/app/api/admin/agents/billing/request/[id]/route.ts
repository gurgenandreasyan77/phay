import { NextResponse } from "next/server";
import type { NextRequest } from "next/server";
import { adminApiFetchRaw } from "@/lib/adminApi";

// Typed by hand: RouteContext only knows routes `next dev`/`next build` has
// already generated types for.
type Ctx = { params: Promise<{ id: string }> };

/** Takes back a request to pay that wasn't paid yet. */
export async function DELETE(_request: NextRequest, ctx: Ctx) {
  const { id } = await ctx.params;
  const result = await adminApiFetchRaw(`/admin/agents/billing/request/${encodeURIComponent(id)}`, { method: "DELETE" });
  return NextResponse.json(result.json ?? {}, { status: result.status });
}
