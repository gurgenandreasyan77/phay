import { NextResponse } from "next/server";
import type { NextRequest } from "next/server";
import { adminApiFetchRaw } from "@/lib/adminApi";

/** Adds an Instagram account to watch (read right away). */
export async function POST(request: NextRequest) {
  const body = await request.text();
  const result = await adminApiFetchRaw("/admin/social/competitors", { method: "POST", body });
  return NextResponse.json(result.json ?? {}, { status: result.status });
}
