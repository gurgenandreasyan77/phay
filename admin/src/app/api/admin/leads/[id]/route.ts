import { NextResponse } from "next/server";
import type { NextRequest } from "next/server";
import { adminApiFetch } from "@/lib/adminApi";

export async function PATCH(request: NextRequest, ctx: RouteContext<"/api/admin/leads/[id]">) {
  const { id } = await ctx.params;
  const body = await request.text();
  const result = await adminApiFetch(`/admin/leads/${id}`, { method: "PATCH", body });
  return NextResponse.json({ data: result.data }, { status: result.ok ? 200 : result.status });
}
