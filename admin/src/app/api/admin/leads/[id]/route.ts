import type { NextRequest } from "next/server";
import { bffResponse } from "@/lib/bff";
import { adminApiFetch } from "@/lib/adminApi";

export async function PATCH(request: NextRequest, ctx: RouteContext<"/api/admin/leads/[id]">) {
  const { id } = await ctx.params;
  const body = await request.text();
  const result = await adminApiFetch(`/admin/leads/${id}`, { method: "PATCH", body });
  return bffResponse(result);
}
