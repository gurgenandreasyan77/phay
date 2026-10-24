import type { NextRequest } from "next/server";
import { proxy } from "@/lib/adminProxy";

// Typed by hand: RouteContext only knows routes `next dev`/`next build` has
// already generated types for.
type Ctx = { params: Promise<{ path: string[] }> };

/** The person's own account security (/admin/security/*): 2FA, codes, devices. */
async function handle(request: NextRequest, ctx: Ctx) {
  const { path } = await ctx.params;
  return proxy(request, `/admin/security/${path.map(encodeURIComponent).join("/")}`);
}

export { handle as POST, handle as PUT, handle as DELETE };
