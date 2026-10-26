import type { NextRequest } from "next/server";
import { bffResponse } from "@/lib/bff";
import { adminApiFetch } from "@/lib/adminApi";

type Ctx = { params: Promise<{ id: string }> };

export const maxDuration = 120;

export async function POST(_request: NextRequest, ctx: Ctx) {
  const { id } = await ctx.params;
  const result = await adminApiFetch(`/admin/table-reshapes/${encodeURIComponent(id)}/apply`, { method: "POST" });
  return bffResponse(result);
}
