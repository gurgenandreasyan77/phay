import type { NextRequest } from "next/server";
import { proxyApiFile } from "@/lib/bffFile";

type Ctx = { params: Promise<{ id: string }> };

// One table as XLSX or CSV (?format=csv), streamed from the API.
export async function GET(request: NextRequest, ctx: Ctx) {
  const { id } = await ctx.params;
  return proxyApiFile(`/admin/data-tables/${encodeURIComponent(id)}/export${request.nextUrl.search}`);
}
