import { NextResponse } from "next/server";
import { adminApiFetchRaw } from "@/lib/adminApi";

export async function GET() {
  const result = await adminApiFetchRaw("/admin/assistant/conversations");
  return NextResponse.json(result.json ?? {}, { status: result.status });
}
