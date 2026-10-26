import type { NextRequest } from "next/server";
import { bffResponse } from "@/lib/bff";
import { adminApiFetch } from "@/lib/adminApi";

type Ctx = { params: Promise<{ id: string; key: string }> };

export async function PATCH(request: NextRequest, ctx: Ctx) {
  const { id, key } = await ctx.params;
  const body = await request.text();
  return bffResponse(await adminApiFetch(`/admin/tenants/${id}/agents/${encodeURIComponent(key)}`, { method: "PATCH", body }));
}
