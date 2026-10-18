import { NextResponse } from "next/server";
import type { NextRequest } from "next/server";
import { adminApiFetchRaw } from "@/lib/adminApi";

/** Groups of employees and their managers: the performer field groups names by them. */
export async function GET() {
  const result = await adminApiFetchRaw("/admin/employee-groups");
  return NextResponse.json(result.json ?? {}, { status: result.status });
}

export async function POST(request: NextRequest) {
  const body = await request.text();
  const result = await adminApiFetchRaw("/admin/employee-groups", { method: "POST", body });
  return NextResponse.json(result.json ?? {}, { status: result.status });
}
