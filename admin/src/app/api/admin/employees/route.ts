import { NextResponse } from "next/server";
import type { NextRequest } from "next/server";
import { adminApiFetchRaw } from "@/lib/adminApi";

/** The business's employees: the performer field offers them. */
export async function GET() {
  const result = await adminApiFetchRaw("/admin/employees");
  return NextResponse.json(result.json ?? {}, { status: result.status });
}

export async function POST(request: NextRequest) {
  const body = await request.text();
  const result = await adminApiFetchRaw("/admin/employees", { method: "POST", body });
  return NextResponse.json(result.json ?? {}, { status: result.status });
}
