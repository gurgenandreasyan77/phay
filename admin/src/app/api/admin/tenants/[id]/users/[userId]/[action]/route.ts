import { NextResponse } from "next/server";
import type { NextRequest } from "next/server";
import { proxy } from "@/lib/adminProxy";

type Ctx = { params: Promise<{ id: string; userId: string; action: string }> };

/** invitation / password-reset links (POST) and resetting a lost 2FA (DELETE two-factor). */
const ACTIONS: Record<string, string[]> = { invitation: ["POST"], "password-reset": ["POST"], "two-factor": ["DELETE"] };

async function handle(request: NextRequest, ctx: Ctx) {
  const { id, userId, action } = await ctx.params;
  if (!ACTIONS[action]?.includes(request.method)) return NextResponse.json({ message: "Not found" }, { status: 404 });
  return proxy(request, `/admin/tenants/${encodeURIComponent(id)}/users/${encodeURIComponent(userId)}/${action}`);
}

export { handle as POST, handle as DELETE };
