import { NextResponse } from "next/server";
import type { NextRequest } from "next/server";
import { adminApiFetch } from "@/lib/adminApi";

export async function GET() {
  const result = await adminApiFetch("/admin/platform-users");
  return NextResponse.json({ data: result.data }, { status: result.ok ? 200 : result.status });
}

export async function POST(request: NextRequest) {
  const body = await request.text();
  const result = await adminApiFetch("/admin/platform-users", { method: "POST", body });
  return NextResponse.json({ data: result.data }, { status: result.ok ? 201 : result.status });
}
