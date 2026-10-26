import type { NextRequest } from "next/server";
import { bffResponse } from "@/lib/bff";
import { adminApiFetch } from "@/lib/adminApi";

// Typed by hand: RouteContext only knows routes `next dev`/`next build` has
// already generated types for.
type Ctx = { params: Promise<{ id: string; key: string }> };

export async function PATCH(request: NextRequest, ctx: Ctx) {
  const { id, key } = await ctx.params;
  const body = await request.text();
  const result = await adminApiFetch(`/admin/tenants/${id}/features/${key}`, { method: "PATCH", body });
  return bffResponse(result);
}
