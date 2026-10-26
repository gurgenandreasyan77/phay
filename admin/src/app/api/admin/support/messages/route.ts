import type { NextRequest } from "next/server";
import { bffResponse } from "@/lib/bff";
import { adminApiFetch } from "@/lib/adminApi";

// The business side of the chat with the platform team.
export async function GET() {
  const result = await adminApiFetch("/admin/support/messages");
  return bffResponse(result);
}

export async function POST(request: NextRequest) {
  const body = await request.text();
  const result = await adminApiFetch("/admin/support/messages", { method: "POST", body });
  return bffResponse(result, { status: 201 });
}
