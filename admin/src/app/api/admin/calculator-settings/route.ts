import { NextResponse } from "next/server";
import { revalidateSite } from "@/lib/siteRevalidate";
import type { NextRequest } from "next/server";
import { adminApiFetch } from "@/lib/adminApi";
import { CALCULATOR_SETTINGS_TAG } from "@/lib/api";

export async function PUT(request: NextRequest) {
  const body = await request.text();
  const result = await adminApiFetch("/admin/calculator-settings", { method: "PUT", body });
  if (result.ok) await revalidateSite(CALCULATOR_SETTINGS_TAG);
  return NextResponse.json({ data: result.data }, { status: result.ok ? 200 : result.status });
}

/** Adds the calculator to the business's services (201), or returns the one it has. */
export async function POST() {
  const result = await adminApiFetch("/admin/calculator-settings", { method: "POST" });
  if (result.ok) await revalidateSite(CALCULATOR_SETTINGS_TAG);
  return NextResponse.json({ data: result.data }, { status: result.status });
}
