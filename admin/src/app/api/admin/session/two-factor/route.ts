import type { NextRequest } from "next/server";
import { callApi, signInFrom } from "@/lib/adminProxy";

/** Sign-in, step two: the challenge and a code from the app (or a recovery code). */
export async function POST(request: NextRequest) {
  const body = await request.text();
  return signInFrom(await callApi(request, "/admin/login/two-factor", { method: "POST", body, auth: false }));
}
