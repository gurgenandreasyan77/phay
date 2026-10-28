import type { NextRequest } from "next/server";
import { bffResponse } from "@/lib/bff";
import { adminApiFetch } from "@/lib/adminApi";

type Ctx = { params: Promise<{ id: string }> };

// A business's balance and ledger, from the platform level (super admin).
export async function GET(_request: NextRequest, ctx: Ctx) {
  const { id } = await ctx.params;
  const result = await adminApiFetch(`/admin/tenants/${id}/balance`);
  return bffResponse(result);
}
