import { NextResponse } from "next/server";
import type { NextRequest } from "next/server";
import { adminApiFetchRaw } from "@/lib/adminApi";

// Typed by hand: RouteContext only knows routes `next dev`/`next build` has
// already generated types for.
type Ctx = { params: Promise<{ id: string }> };

/** Extra AI assistant requests for the business (negative: take some back). */
export async function POST(request: NextRequest, ctx: Ctx) {
  const { id } = await ctx.params;
  const body = await request.text();
  const result = await adminApiFetchRaw(`/admin/tenants/${id}/subscription/assistant-bonus`, { method: "POST", body });
  return NextResponse.json(result.json ?? {}, { status: result.status });
}
