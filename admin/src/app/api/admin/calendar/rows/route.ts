import { NextResponse } from "next/server";
import type { NextRequest } from "next/server";
import { adminApiFetchRaw } from "@/lib/adminApi";

/** An order added from the calendar: the API puts it in the table of its month. */
export async function POST(request: NextRequest) {
  const body = await request.text();
  const result = await adminApiFetchRaw("/admin/calendar/rows", { method: "POST", body });
  return NextResponse.json(result.json ?? {}, { status: result.status });
}
