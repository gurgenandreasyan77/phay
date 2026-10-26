import type { NextRequest } from "next/server";
import { bffResponse } from "@/lib/bff";
import { adminApiFetch } from "@/lib/adminApi";
import { proxy } from "@/lib/adminProxy";

// Typed by hand: RouteContext only knows routes `next dev`/`next build` has
// already generated types for.
type Ctx = { params: Promise<{ id: string }> };

export async function GET(_request: NextRequest, ctx: Ctx) {
  const { id } = await ctx.params;
  const result = await adminApiFetch(`/admin/tenants/${id}/users`);
  return bffResponse(result);
}

// The whole body comes back: the new login and its invitation link.
export async function POST(request: NextRequest, ctx: Ctx) {
  const { id } = await ctx.params;
  return proxy(request, `/admin/tenants/${encodeURIComponent(id)}/users`);
}
