import { NextResponse } from "next/server";
import { adminApiFetchRaw } from "@/lib/adminApi";

/** Reads the chosen calendars' new, changed and deleted events. */
export async function POST() {
  const result = await adminApiFetchRaw("/admin/google/sync", { method: "POST" });
  return NextResponse.json(result.json ?? {}, { status: result.status });
}
