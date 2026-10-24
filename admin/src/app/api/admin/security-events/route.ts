import type { NextRequest } from "next/server";
import { proxy } from "@/lib/adminProxy";

/** The platform's security log (super admin). */
export async function GET(request: NextRequest) {
  return proxy(request, `/admin/security-events${request.nextUrl.search}`);
}
