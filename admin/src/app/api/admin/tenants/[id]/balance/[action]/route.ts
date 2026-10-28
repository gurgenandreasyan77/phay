import { NextResponse } from "next/server";
import type { NextRequest } from "next/server";
import { adminApiFetchRaw } from "@/lib/adminApi";

type Ctx = { params: Promise<{ id: string; action: string }> };

const ACTIONS = new Set(["top-ups", "corrections"]);

/** The super admin tops a business's balance up (a receipt in the chat) or corrects it. */
export async function POST(request: NextRequest, ctx: Ctx) {
  const { id, action } = await ctx.params;
  if (!ACTIONS.has(action)) return NextResponse.json({ message: "Not found." }, { status: 404 });
  const body = await request.text();
  const result = await adminApiFetchRaw(`/admin/tenants/${id}/balance/${action}`, { method: "POST", body });
  return NextResponse.json(result.json ?? {}, { status: result.status });
}
