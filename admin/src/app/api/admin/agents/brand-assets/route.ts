import { NextResponse } from "next/server";
import type { NextRequest } from "next/server";
import { adminApiFetchRaw } from "@/lib/adminApi";
import { proxyApiUpload } from "@/lib/bffFile";

// A picture for the AI agents: uploaded (multipart) or one attached in the
// chat ({ attachment_id, kind } as JSON).
export async function POST(request: NextRequest) {
  if (request.headers.get("content-type")?.startsWith("multipart/form-data")) {
    return proxyApiUpload("/admin/agents/brand-assets", request);
  }
  const result = await adminApiFetchRaw("/admin/agents/brand-assets", { method: "POST", body: await request.text() });
  return NextResponse.json(result.json ?? {}, { status: result.status });
}
