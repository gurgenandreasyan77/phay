import { NextResponse } from "next/server";
import type { NextRequest } from "next/server";
import { adminApiFetch } from "@/lib/adminApi";
import { proxy } from "@/lib/adminProxy";

export async function GET() {
  const result = await adminApiFetch("/admin/platform-users");
  return NextResponse.json({ data: result.data }, { status: result.ok ? 200 : result.status });
}

// The whole body comes back: the new member and their invitation link.
export async function POST(request: NextRequest) {
  return proxy(request, "/admin/platform-users");
}
