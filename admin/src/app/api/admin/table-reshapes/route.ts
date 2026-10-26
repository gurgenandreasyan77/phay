import type { NextRequest } from "next/server";
import { bffResponse } from "@/lib/bff";
import { adminApiFetch } from "@/lib/adminApi";

// Rows of many tables are moved at once.
export const maxDuration = 120;

export async function POST(request: NextRequest) {
  const body = await request.text();
  const result = await adminApiFetch("/admin/table-reshapes", { method: "POST", body });
  return bffResponse(result, { status: 201 });
}
