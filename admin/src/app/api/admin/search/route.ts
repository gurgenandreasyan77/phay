import type { NextRequest } from "next/server";
import { bffResponse } from "@/lib/bff";
import { adminApiFetch } from "@/lib/adminApi";

export async function GET(request: NextRequest) {
  const q = request.nextUrl.searchParams.get("q") ?? "";
  const result = await adminApiFetch(`/admin/search?q=${encodeURIComponent(q)}`);
  return bffResponse(result);
}
