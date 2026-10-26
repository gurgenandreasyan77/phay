import type { NextRequest } from "next/server";
import { bffResponse } from "@/lib/bff";
import { adminApiFetch } from "@/lib/adminApi";

export async function PUT(request: NextRequest) {
  const body = await request.text();
  const result = await adminApiFetch("/admin/calculator-settings", { method: "PUT", body });
  return bffResponse(result);
}

/** Adds the calculator to the business's services (201), or returns the one it has. */
export async function POST() {
  const result = await adminApiFetch("/admin/calculator-settings", { method: "POST" });
  return bffResponse(result, { status: result.status });
}
