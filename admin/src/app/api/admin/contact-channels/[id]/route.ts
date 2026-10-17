import { NextResponse } from "next/server";
import { revalidateSite } from "@/lib/siteRevalidate";
import type { NextRequest } from "next/server";
import { adminApiFetch } from "@/lib/adminApi";
import { SITE_SETTINGS_TAG } from "@/lib/api";

export async function PATCH(request: NextRequest, ctx: RouteContext<"/api/admin/contact-channels/[id]">) {
  const { id } = await ctx.params;
  const body = await request.text();
  const result = await adminApiFetch(`/admin/contact-channels/${id}`, { method: "PATCH", body });
  if (result.ok) await revalidateSite(SITE_SETTINGS_TAG);
  return NextResponse.json({ data: result.data }, { status: result.ok ? 200 : result.status });
}

export async function DELETE(_request: NextRequest, ctx: RouteContext<"/api/admin/contact-channels/[id]">) {
  const { id } = await ctx.params;
  const result = await adminApiFetch(`/admin/contact-channels/${id}`, { method: "DELETE" });
  if (result.ok) await revalidateSite(SITE_SETTINGS_TAG);
  return NextResponse.json({}, { status: result.ok ? 200 : result.status });
}
