import { NextResponse } from "next/server";
import type { NextRequest } from "next/server";
import { adminApiFetch } from "@/lib/adminApi";

export async function GET(request: NextRequest) {
  const q = request.nextUrl.searchParams.get("q") ?? "";
  const result = await adminApiFetch(`/admin/search?q=${encodeURIComponent(q)}`);
  return NextResponse.json({ data: result.data }, { status: result.ok ? 200 : result.status });
}
