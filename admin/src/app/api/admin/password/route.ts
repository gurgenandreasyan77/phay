import { NextResponse } from "next/server";
import type { NextRequest } from "next/server";
import { adminApiFetchRaw } from "@/lib/adminApi";

// The whole body comes back, so the form can say which field was wrong.
export async function PUT(request: NextRequest) {
  const body = await request.text();
  const result = await adminApiFetchRaw("/admin/password", { method: "PUT", body });
  return NextResponse.json(result.json, { status: result.status });
}
