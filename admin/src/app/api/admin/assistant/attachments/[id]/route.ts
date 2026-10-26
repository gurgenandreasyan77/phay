import { NextResponse } from "next/server";
import type { NextRequest } from "next/server";
import { adminApiFetchRaw } from "@/lib/adminApi";

// Typed by hand: RouteContext only knows routes `next dev`/`next build` has
// already generated types for.
type Ctx = { params: Promise<{ id: string }> };

/** Takes back a file not sent yet. */
export async function DELETE(_request: NextRequest, ctx: Ctx) {
  const { id } = await ctx.params;
  const result = await adminApiFetchRaw(`/admin/assistant/attachments/${encodeURIComponent(id)}`, { method: "DELETE" });
  return NextResponse.json(result.status === 204 ? {} : (result.json ?? {}), {
    status: result.status === 204 ? 200 : result.status,
  });
}
