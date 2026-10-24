import { NextResponse } from "next/server";
import type { NextRequest } from "next/server";
import { adminApiFetch } from "@/lib/adminApi";

export async function PUT(request: NextRequest) {
  const body = await request.text();
  const result = await adminApiFetch("/admin/calculator-settings", { method: "PUT", body });
  return NextResponse.json({ data: result.data }, { status: result.ok ? 200 : result.status });
}

/** Adds the calculator to the business's services (201), or returns the one it has. */
export async function POST() {
  const result = await adminApiFetch("/admin/calculator-settings", { method: "POST" });
  return NextResponse.json({ data: result.data }, { status: result.status });
}
