import { NextResponse } from "next/server";
import type { NextRequest } from "next/server";
import { adminApiFetchRaw } from "@/lib/adminApi";

type Ctx = { params: Promise<{ id: string }> };

export async function POST(_request: NextRequest, ctx: Ctx) {
  const { id } = await ctx.params;
  const result = await adminApiFetchRaw(`/admin/data-imports/${id}/undo`, { method: "POST" });
  return NextResponse.json(result.json ?? {}, { status: result.status });
}
