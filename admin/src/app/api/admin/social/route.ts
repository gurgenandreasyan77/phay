import { NextResponse } from "next/server";
import { adminApiFetchRaw } from "@/lib/adminApi";

// The business's Instagram / Facebook connection; DELETE forgets it and everything read from Meta.
export async function GET() {
  const result = await adminApiFetchRaw("/admin/social");
  return NextResponse.json(result.json ?? {}, { status: result.status });
}

export async function DELETE() {
  const result = await adminApiFetchRaw("/admin/social", { method: "DELETE" });
  return result.status === 204 ? new NextResponse(null, { status: 204 }) : NextResponse.json(result.json ?? {}, { status: result.status });
}
