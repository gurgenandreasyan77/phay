import { NextResponse } from "next/server";
import type { NextRequest } from "next/server";
import { adminApiFetchRaw } from "@/lib/adminApi";

type Ctx = { params: Promise<{ id: string }> };

/** A video an agent is cutting: asked about until it is ready. */
export async function GET(_request: NextRequest, ctx: Ctx) {
  const { id } = await ctx.params;
  const result = await adminApiFetchRaw(`/admin/assistant/videos/${encodeURIComponent(id)}`);
  return NextResponse.json(result.json ?? {}, { status: result.status });
}
