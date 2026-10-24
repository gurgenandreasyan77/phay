import type { NextRequest } from "next/server";
import { proxy } from "@/lib/adminProxy";

export async function POST(request: NextRequest) {
  return proxy(request, "/admin/password/forgot", { auth: false });
}
