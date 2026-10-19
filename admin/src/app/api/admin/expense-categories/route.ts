import { NextResponse } from "next/server";
import type { NextRequest } from "next/server";
import { adminApiFetchRaw } from "@/lib/adminApi";

/** Kinds of the business's expenses: the ready-made ones and its own. */
export async function GET() {
  const result = await adminApiFetchRaw("/admin/expense-categories");
  return NextResponse.json(result.json ?? {}, { status: result.status });
}

export async function POST(request: NextRequest) {
  const body = await request.text();
  const result = await adminApiFetchRaw("/admin/expense-categories", { method: "POST", body });
  return NextResponse.json(result.json ?? {}, { status: result.status });
}
