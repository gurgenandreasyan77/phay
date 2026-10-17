import { NextResponse } from "next/server";
import type { NextRequest } from "next/server";
import { adminApiFetchRaw } from "@/lib/adminApi";

// The model can take a while to think the answer through.
export const maxDuration = 120;

export async function POST(request: NextRequest) {
  const body = await request.text();
  const result = await adminApiFetchRaw("/admin/assistant", { method: "POST", body });
  return NextResponse.json(result.json ?? {}, { status: result.status });
}
