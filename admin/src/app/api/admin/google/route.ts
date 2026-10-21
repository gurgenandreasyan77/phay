import { NextResponse } from "next/server";
import { adminApiFetchRaw } from "@/lib/adminApi";

// The person's Google Calendar connection; DELETE forgets it (tables stay).
export async function GET() {
  const result = await adminApiFetchRaw("/admin/google");
  return NextResponse.json(result.json ?? {}, { status: result.status });
}

export async function DELETE() {
  const result = await adminApiFetchRaw("/admin/google", { method: "DELETE" });
  return result.status === 204 ? new NextResponse(null, { status: 204 }) : NextResponse.json(result.json ?? {}, { status: result.status });
}
