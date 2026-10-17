import { NextResponse } from "next/server";
import type { NextRequest } from "next/server";
import { adminApiFetch } from "@/lib/adminApi";

export async function POST(_request: NextRequest, ctx: RouteContext<"/api/admin/sites/[id]/rotate-key">) {
  const { id } = await ctx.params;
  const result = await adminApiFetch(`/admin/sites/${id}/rotate-key`, { method: "POST" });
  return NextResponse.json({ data: result.data }, { status: result.ok ? 200 : result.status });
}
