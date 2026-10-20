import { NextResponse } from "next/server";
import type { NextRequest } from "next/server";
import { adminApiFetchRaw } from "@/lib/adminApi";

export async function POST(request: NextRequest) {
  const body = await request.text();
  const { status, json } = await adminApiFetchRaw("/admin/posts", { method: "POST", body });
  return NextResponse.json(json ?? {}, { status });
}
