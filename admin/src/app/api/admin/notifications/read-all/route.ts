import { NextResponse } from "next/server";
import { adminApiFetch } from "@/lib/adminApi";

export async function POST() {
  const result = await adminApiFetch("/admin/notifications/read-all", { method: "POST" });
  return new NextResponse(null, { status: result.ok ? 204 : result.status });
}
