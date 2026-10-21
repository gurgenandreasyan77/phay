import { NextResponse } from "next/server";
import { adminApiFetchRaw } from "@/lib/adminApi";

/** What «turn into a table» would do (GET), and doing it (POST): a new version when something changed. */
export async function GET() {
  const result = await adminApiFetchRaw("/admin/google/table");
  return NextResponse.json(result.json ?? {}, { status: result.status });
}

export async function POST() {
  const result = await adminApiFetchRaw("/admin/google/table", { method: "POST" });
  return NextResponse.json(result.json ?? {}, { status: result.status });
}
