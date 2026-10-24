import type { NextRequest } from "next/server";
import { proxy } from "@/lib/adminProxy";

/** Who an invitation link is for (the token comes in the body). */
export async function POST(request: NextRequest) {
  return proxy(request, "/admin/invitations/lookup", { auth: false });
}
