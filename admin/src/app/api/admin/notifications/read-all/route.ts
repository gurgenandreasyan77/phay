import { NextResponse } from "next/server";
import { bffResponse } from "@/lib/bff";
import { adminApiFetch } from "@/lib/adminApi";

export async function POST() {
  const result = await adminApiFetch("/admin/notifications/read-all", { method: "POST" });
  return result.ok ? new NextResponse(null, { status: 204 }) : bffResponse(result);
}
