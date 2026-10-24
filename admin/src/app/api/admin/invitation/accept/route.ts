import type { NextRequest } from "next/server";
import { callApi, signInFrom } from "@/lib/adminProxy";

/** The invited person sets their first password and is signed in. */
export async function POST(request: NextRequest) {
  const body = await request.text();
  return signInFrom(await callApi(request, "/admin/invitations/accept", { method: "POST", body, auth: false }));
}
