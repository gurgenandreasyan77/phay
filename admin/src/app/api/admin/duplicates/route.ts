import { NextResponse } from "next/server";
import type { NextRequest } from "next/server";
import { adminApiFetchRaw } from "@/lib/adminApi";

export async function GET(request: NextRequest) {
  const section = request.nextUrl.searchParams.get("section");
  const result = await adminApiFetchRaw(`/admin/duplicates${section ? `?section=${encodeURIComponent(section)}` : ""}`);
  return NextResponse.json(result.json ?? {}, { status: result.status });
}
