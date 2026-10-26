import type { NextRequest } from "next/server";
import { bffResponse } from "@/lib/bff";
import { adminApiFetch } from "@/lib/adminApi";

type Context = { params: Promise<{ id: string }> };

export async function PATCH(request: NextRequest, ctx: Context) {
  const { id } = await ctx.params;
  const body = await request.text();
  const result = await adminApiFetch(`/admin/feedback/${id}`, { method: "PATCH", body });
  return bffResponse(result);
}

export async function DELETE(_request: NextRequest, ctx: Context) {
  const { id } = await ctx.params;
  const result = await adminApiFetch(`/admin/feedback/${id}`, { method: "DELETE" });
  return bffResponse(result, { empty: true });
}
