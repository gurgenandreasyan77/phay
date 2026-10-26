import { NextResponse } from "next/server";
import type { NextRequest } from "next/server";
import { adminApiFetchRaw } from "@/lib/adminApi";

/** Which Pages and Instagram accounts are the business's. */
export async function PUT(request: NextRequest) {
  const body = await request.text();
  const result = await adminApiFetchRaw("/admin/social/accounts", { method: "PUT", body });
  return NextResponse.json(result.json ?? {}, { status: result.status });
}
