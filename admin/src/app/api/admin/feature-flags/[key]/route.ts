import { NextResponse } from "next/server";
import { revalidateSite } from "@/lib/siteRevalidate";
import type { NextRequest } from "next/server";
import { adminApiFetch } from "@/lib/adminApi";
import { SITE_SETTINGS_TAG } from "@/lib/api";

export async function PATCH(request: NextRequest, ctx: RouteContext<"/api/admin/feature-flags/[key]">) {
  const { key } = await ctx.params;
  const body = await request.text();
  const result = await adminApiFetch(`/admin/feature-flags/${key}`, { method: "PATCH", body });
  // The public /v1/settings "features" block is cached — without this, a
  // toggle wouldn't show up on the public site until the 1-hour ISR window
  // expired, even though the admin screen itself always reads fresh.
  if (result.ok) await revalidateSite(SITE_SETTINGS_TAG);
  return NextResponse.json({ data: result.data }, { status: result.ok ? 200 : result.status });
}
