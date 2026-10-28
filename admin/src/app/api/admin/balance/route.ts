import type { NextRequest } from "next/server";
import { bffResponse } from "@/lib/bff";
import { adminApiFetch } from "@/lib/adminApi";

// The business's own balance and whether what ends renews from it.
export async function GET() {
  const result = await adminApiFetch("/admin/balance");
  return bffResponse(result);
}

export async function PATCH(request: NextRequest) {
  const body = await request.text();
  const result = await adminApiFetch("/admin/balance", { method: "PATCH", body });
  return bffResponse(result);
}
