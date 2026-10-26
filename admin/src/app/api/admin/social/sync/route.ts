import { NextResponse } from "next/server";
import { adminApiFetchRaw } from "@/lib/adminApi";

/** Reads the chosen accounts and the watched ones from Meta now. */
export async function POST() {
  const result = await adminApiFetchRaw("/admin/social/sync", { method: "POST" });
  return NextResponse.json(result.json ?? {}, { status: result.status });
}
