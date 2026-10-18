import { NextResponse } from "next/server";
import type { NextRequest } from "next/server";
import { adminApiFetch } from "@/lib/adminApi";

// The business side of the chat with the platform team.
export async function GET() {
  const result = await adminApiFetch("/admin/support/messages");
  return NextResponse.json({ data: result.data }, { status: result.ok ? 200 : result.status });
}

export async function POST(request: NextRequest) {
  const body = await request.text();
  const result = await adminApiFetch("/admin/support/messages", { method: "POST", body });
  return NextResponse.json({ data: result.data }, { status: result.ok ? 201 : result.status });
}
