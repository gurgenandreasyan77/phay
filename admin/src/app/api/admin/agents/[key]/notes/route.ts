import type { NextRequest } from "next/server";
import { bffResponse } from "@/lib/bff";
import { adminApiFetch } from "@/lib/adminApi";

// Typed by hand: RouteContext only knows routes `next dev`/`next build` has
// already generated types for.
type Ctx = { params: Promise<{ key: string }> };

export async function PUT(request: NextRequest, ctx: Ctx) {
  const { key } = await ctx.params;
  const body = await request.text();
  return bffResponse(
    await adminApiFetch(`/admin/agents/${encodeURIComponent(key)}/notes`, {
      method: "PUT",
      body,
    }),
  );
}
