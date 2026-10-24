import type { NextRequest } from "next/server";
import { proxy } from "@/lib/adminProxy";

export async function GET(request: NextRequest) {
  return proxy(request, "/admin/security");
}
