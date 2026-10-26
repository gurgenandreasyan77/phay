import type { NextRequest } from "next/server";
import { bffResponse } from "@/lib/bff";
import { adminApiFetch } from "@/lib/adminApi";
import { proxy } from "@/lib/adminProxy";

export async function GET() {
  const result = await adminApiFetch("/admin/platform-users");
  return bffResponse(result);
}

// The whole body comes back: the new member and their invitation link.
export async function POST(request: NextRequest) {
  return proxy(request, "/admin/platform-users");
}
