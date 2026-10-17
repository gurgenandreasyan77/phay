import { NextResponse } from "next/server";
import type { NextRequest } from "next/server";
import { adminApiFetchRaw } from "@/lib/adminApi";

// Raw: validation errors (per column) are shown next to the fields.
export async function POST(request: NextRequest) {
  const body = await request.text();
  const result = await adminApiFetchRaw("/admin/data-tables", { method: "POST", body });
  return NextResponse.json(result.json ?? {}, { status: result.status });
}
