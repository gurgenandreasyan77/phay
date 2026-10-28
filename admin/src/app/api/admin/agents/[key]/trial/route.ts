import { NextResponse } from "next/server";
import type { NextRequest } from "next/server";
import { adminApiFetchRaw } from "@/lib/adminApi";

// Typed by hand: RouteContext only knows routes `next dev`/`next build` has
// already generated types for.
type Ctx = { params: Promise<{ key: string }> };

/** Starts an AI agent's free trial (once per agent). */
export async function POST(_request: NextRequest, ctx: Ctx) {
  const { key } = await ctx.params;
  const result = await adminApiFetchRaw(`/admin/agents/${encodeURIComponent(key)}/trial`, { method: "POST" });
  return NextResponse.json(result.json ?? {}, { status: result.status });
}
