import { NextResponse } from "next/server";
import type { NextRequest } from "next/server";
import { adminApiFetchRaw } from "@/lib/adminApi";

/** While an order is typed in: is its performer free then, which number it gets. */
export async function POST(request: NextRequest) {
  const body = await request.text();
  const result = await adminApiFetchRaw("/admin/calendar/rows/check", { method: "POST", body });
  return NextResponse.json(result.json ?? {}, { status: result.status });
}
