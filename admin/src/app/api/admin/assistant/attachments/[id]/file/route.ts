import type { NextRequest } from "next/server";
import { proxyApiFile } from "@/lib/bffFile";

type Ctx = { params: Promise<{ id: string }> };

export async function GET(request: NextRequest, ctx: Ctx) {
  const { id } = await ctx.params;
  const download = request.nextUrl.searchParams.get("download") === "1" ? "?download=1" : "";
  return proxyApiFile(`/admin/assistant/attachments/${encodeURIComponent(id)}/file${download}`, request);
}
