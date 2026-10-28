import { NextResponse } from "next/server";
import type { NextRequest } from "next/server";
import { adminApiFetchRaw } from "@/lib/adminApi";

/** The business asks the platform (in its chat) to pay for some AI agents. */
export async function POST(request: NextRequest) {
  const body = await request.text();
  const result = await adminApiFetchRaw("/admin/agents/billing/request", { method: "POST", body });
  return NextResponse.json(result.json ?? {}, { status: result.status });
}
