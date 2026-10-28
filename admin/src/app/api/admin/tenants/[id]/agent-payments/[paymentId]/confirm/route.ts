import { NextResponse } from "next/server";
import type { NextRequest } from "next/server";
import { adminApiFetchRaw } from "@/lib/adminApi";

// Typed by hand: RouteContext only knows routes `next dev`/`next build` has
// already generated types for.
type Ctx = { params: Promise<{ id: string; paymentId: string }> };

/** The money of a business's request came: its agents move to their new end. */
export async function POST(request: NextRequest, ctx: Ctx) {
  const { id, paymentId } = await ctx.params;
  const body = await request.text();
  const result = await adminApiFetchRaw(`/admin/tenants/${id}/agent-payments/${paymentId}/confirm`, { method: "POST", body });
  return NextResponse.json(result.json ?? {}, { status: result.status });
}
