import type { NextRequest } from "next/server";
import { bffResponse } from "@/lib/bff";
import { adminApiFetch } from "@/lib/adminApi";

type Ctx = { params: Promise<{ id: string }> };

export const maxDuration = 60;

export async function POST(_request: NextRequest, ctx: Ctx) {
  const { id } = await ctx.params;
  const result = await adminApiFetch(`/admin/table-changes/${encodeURIComponent(id)}/apply`, { method: "POST" });
  return bffResponse(result);
}
