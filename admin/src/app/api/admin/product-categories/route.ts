import { NextResponse } from "next/server";
import { revalidateSite } from "@/lib/siteRevalidate";
import type { NextRequest } from "next/server";
import { adminApiFetch } from "@/lib/adminApi";
import { PRODUCT_CATEGORIES_TAG } from "@/lib/api";

export async function POST(request: NextRequest) {
  const body = await request.text();
  const result = await adminApiFetch("/admin/product-categories", { method: "POST", body });
  if (result.ok) await revalidateSite(PRODUCT_CATEGORIES_TAG);
  return NextResponse.json({ data: result.data }, { status: result.ok ? 201 : result.status });
}
