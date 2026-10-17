import { NextResponse } from "next/server";
import { revalidateSite } from "@/lib/siteRevalidate";
import type { NextRequest } from "next/server";
import { adminApiFetch } from "@/lib/adminApi";
import { SITE_SETTINGS_TAG } from "@/lib/api";

export async function POST(request: NextRequest) {
  const body = await request.text();
  const result = await adminApiFetch("/admin/contact-channels", { method: "POST", body });
  if (result.ok) await revalidateSite(SITE_SETTINGS_TAG);
  return NextResponse.json({ data: result.data }, { status: result.ok ? 201 : result.status });
}
