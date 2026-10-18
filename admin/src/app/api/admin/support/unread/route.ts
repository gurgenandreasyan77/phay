import { NextResponse } from "next/server";
import { adminApiFetch } from "@/lib/adminApi";

export async function GET() {
  const result = await adminApiFetch("/admin/support/unread");
  return NextResponse.json({ data: result.data }, { status: result.ok ? 200 : result.status });
}
