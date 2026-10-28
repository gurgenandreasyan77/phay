import { NextResponse } from "next/server";
import type { NextRequest } from "next/server";
import { adminApiFetchRaw } from "@/lib/adminApi";

// Typed by hand: RouteContext only knows routes `next dev`/`next build` has
// already generated types for.
type Ctx = { params: Promise<{ action: string }> };

const ACTIONS = new Set(["subscription", "plan", "module", "agents"]);

/** Pays from the balance: months of the subscription, a bigger plan, a module or AI agents. */
export async function POST(request: NextRequest, ctx: Ctx) {
  const { action } = await ctx.params;
  if (!ACTIONS.has(action)) return NextResponse.json({ message: "Not found." }, { status: 404 });
  const body = await request.text();
  const result = await adminApiFetchRaw(`/admin/balance/${action}`, { method: "POST", body });
  return NextResponse.json(result.json ?? {}, { status: result.status });
}
