import { NextResponse } from "next/server";
import type { NextRequest } from "next/server";
import { adminApiFetchRaw } from "@/lib/adminApi";

export async function GET() {
  const result = await adminApiFetchRaw("/admin/plans");
  return NextResponse.json(result.json ?? {}, { status: result.status });
}

export async function POST(request: NextRequest) {
  const body = await request.text();
  const result = await adminApiFetchRaw("/admin/plans", { method: "POST", body });
  return NextResponse.json(result.json ?? {}, { status: result.status });
}
