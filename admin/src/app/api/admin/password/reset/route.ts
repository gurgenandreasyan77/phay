import type { NextRequest } from "next/server";
import { proxy } from "@/lib/adminProxy";

/** A new password from a reset link; every session is signed out. */
export async function POST(request: NextRequest) {
  return proxy(request, "/admin/password/reset", { auth: false });
}
