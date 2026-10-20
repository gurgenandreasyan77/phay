import { NextResponse } from "next/server";
import type { NextRequest } from "next/server";
import { adminApiFetch } from "@/lib/adminApi";

export async function POST(_request: NextRequest, ctx: { params: Promise<{ id: string }> }) {
  const { id } = await ctx.params;
  const result = await adminApiFetch(`/admin/notifications/${encodeURIComponent(id)}/read`, { method: "POST" });
  return new NextResponse(null, { status: result.ok ? 204 : result.status });
}
