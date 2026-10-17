import { NextResponse } from "next/server";
import type { NextRequest } from "next/server";
import { adminApiFetchRaw } from "@/lib/adminApi";

type Ctx = { params: Promise<{ id: string }> };

// A big file takes a while to parse and insert.
export const maxDuration = 120;

export async function POST(request: NextRequest, ctx: Ctx) {
  const { id } = await ctx.params;
  const body = await request.text();
  const result = await adminApiFetchRaw(`/admin/data-imports/${id}/run`, { method: "POST", body });
  return NextResponse.json(result.json ?? {}, { status: result.status });
}
