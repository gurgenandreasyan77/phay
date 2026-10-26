import type { NextRequest } from "next/server";
import { proxyApiFile } from "@/lib/bffFile";

type Ctx = { params: Promise<{ id: string }> };

export async function GET(_request: NextRequest, ctx: Ctx) {
  const { id } = await ctx.params;
  return proxyApiFile(`/admin/agents/brand-assets/${encodeURIComponent(id)}/file`);
}
