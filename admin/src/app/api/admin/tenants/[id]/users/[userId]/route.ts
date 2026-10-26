import { NextResponse } from "next/server";
import type { NextRequest } from "next/server";
import { bffResponse } from "@/lib/bff";
import { adminApiFetch } from "@/lib/adminApi";

// Typed by hand: RouteContext only knows routes `next dev`/`next build` has
// already generated types for.
type Ctx = { params: Promise<{ id: string; userId: string }> };

export async function PATCH(request: NextRequest, ctx: Ctx) {
  const { id, userId } = await ctx.params;
  const body = await request.text();
  const result = await adminApiFetch(`/admin/tenants/${id}/users/${userId}`, {
    method: "PATCH",
    body,
  });
  return bffResponse(result);
}

export async function DELETE(_request: NextRequest, ctx: Ctx) {
  const { id, userId } = await ctx.params;
  const result = await adminApiFetch(`/admin/tenants/${id}/users/${userId}`, {
    method: "DELETE",
  });
  return result.ok
    ? new NextResponse(null, { status: 204 })
    : bffResponse(result);
}
