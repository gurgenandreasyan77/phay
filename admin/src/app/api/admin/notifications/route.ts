import { NextResponse } from "next/server";
import { adminApiFetchRaw } from "@/lib/adminApi";

// The bell: notifications and the unread count (meta.unread) as Laravel gives them.
export async function GET() {
  const result = await adminApiFetchRaw("/admin/notifications");
  return NextResponse.json(result.json ?? {}, { status: result.status });
}
