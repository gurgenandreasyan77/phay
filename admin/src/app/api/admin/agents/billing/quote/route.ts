import { NextResponse } from "next/server";
import type { NextRequest } from "next/server";
import { adminApiFetchRaw } from "@/lib/adminApi";

/** What paying for some AI agents would cost: months each, or all to one common day. */
export async function POST(request: NextRequest) {
  const body = await request.text();
  const result = await adminApiFetchRaw("/admin/agents/billing/quote", { method: "POST", body });
  return NextResponse.json(result.json ?? {}, { status: result.status });
}
