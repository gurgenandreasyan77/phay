import type { NextRequest } from "next/server";
import { bffResponse } from "@/lib/bff";
import { adminApiFetch } from "@/lib/adminApi";

export async function PATCH(request: NextRequest, ctx: RouteContext<"/api/admin/service-packages/[id]">) {
  const { id } = await ctx.params;
  const body = await request.text();
  const result = await adminApiFetch(`/admin/service-packages/${id}`, { method: "PATCH", body });
  return bffResponse(result);
}

export async function DELETE(_request: NextRequest, ctx: RouteContext<"/api/admin/service-packages/[id]">) {
  const { id } = await ctx.params;
  const result = await adminApiFetch(`/admin/service-packages/${id}`, { method: "DELETE" });
  return bffResponse(result, { empty: true });
}
