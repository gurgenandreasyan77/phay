import { NextResponse } from "next/server";
import type { NextRequest } from "next/server";
import { adminApiFetchRaw } from "@/lib/adminApi";

/** Expenses that repeat every week, month, quarter or year. */
export async function GET() {
  const result = await adminApiFetchRaw("/admin/recurring-expenses");
  return NextResponse.json(result.json ?? {}, { status: result.status });
}

export async function POST(request: NextRequest) {
  const body = await request.text();
  const result = await adminApiFetchRaw("/admin/recurring-expenses", { method: "POST", body });
  return NextResponse.json(result.json ?? {}, { status: result.status });
}
