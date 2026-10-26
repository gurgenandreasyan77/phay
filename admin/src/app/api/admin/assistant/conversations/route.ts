import { NextResponse } from "next/server";
import type { NextRequest } from "next/server";
import { adminApiFetchRaw } from "@/lib/adminApi";

// ?agent=<key>: the chats with one AI agent.
export async function GET(request: NextRequest) {
  const agent = request.nextUrl.searchParams.get("agent");
  const query = agent ? `?agent=${encodeURIComponent(agent)}` : "";
  const result = await adminApiFetchRaw(`/admin/assistant/conversations${query}`);
  return NextResponse.json(result.json ?? {}, { status: result.status });
}
