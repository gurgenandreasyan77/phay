import type { NextRequest } from "next/server";
import { bffResponse } from "@/lib/bff";
import { adminApiFetch } from "@/lib/adminApi";

// Typed by hand: RouteContext only knows routes `next dev`/`next build` has
// already generated types for.
type Ctx = { params: Promise<{ id: string }> };

// The platform side of the chat with one business.
export async function GET(_request: NextRequest, ctx: Ctx) {
  const { id } = await ctx.params;
  const result = await adminApiFetch(`/admin/tenants/${id}/messages`);
  return bffResponse(result);
}

export async function POST(request: NextRequest, ctx: Ctx) {
  const { id } = await ctx.params;
  const body = await request.text();
  const result = await adminApiFetch(`/admin/tenants/${id}/messages`, { method: "POST", body });
  return bffResponse(result, { status: 201 });
}
