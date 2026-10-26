import type { NextRequest } from "next/server";
import { bffResponse } from "@/lib/bff";
import { adminApiFetch } from "@/lib/adminApi";

export async function PUT(request: NextRequest) {
  const body = await request.text();
  return bffResponse(await adminApiFetch("/admin/agents/profile", { method: "PUT", body }));
}
