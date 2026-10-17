import { NextResponse } from "next/server";
import { revalidateSite } from "@/lib/siteRevalidate";
import type { NextRequest } from "next/server";
import { adminApiFetch } from "@/lib/adminApi";
import { PRODUCTS_TAG } from "@/lib/api";

export async function PATCH(request: NextRequest, ctx: RouteContext<"/api/admin/products/[id]">) {
  const { id } = await ctx.params;
  const body = await request.text();
  const result = await adminApiFetch(`/admin/products/${id}`, { method: "PATCH", body });
  if (result.ok) await revalidateSite(PRODUCTS_TAG);
  return NextResponse.json({ data: result.data }, { status: result.ok ? 200 : result.status });
}

export async function DELETE(_request: NextRequest, ctx: RouteContext<"/api/admin/products/[id]">) {
  const { id } = await ctx.params;
  const result = await adminApiFetch(`/admin/products/${id}`, { method: "DELETE" });
  if (result.ok) await revalidateSite(PRODUCTS_TAG);
  return NextResponse.json({}, { status: result.ok ? 200 : result.status });
}
