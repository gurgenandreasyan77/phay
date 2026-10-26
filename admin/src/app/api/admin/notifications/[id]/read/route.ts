import { NextResponse } from "next/server";
import type { NextRequest } from "next/server";
import { bffResponse } from "@/lib/bff";
import { adminApiFetch } from "@/lib/adminApi";

export async function POST(_request: NextRequest, ctx: { params: Promise<{ id: string }> }) {
  const { id } = await ctx.params;
  const result = await adminApiFetch(`/admin/notifications/${encodeURIComponent(id)}/read`, { method: "POST" });
  return result.ok ? new NextResponse(null, { status: 204 }) : bffResponse(result);
}
