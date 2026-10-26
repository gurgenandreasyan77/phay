import type { NextRequest } from "next/server";
import { proxyApiUpload } from "@/lib/bffFile";

// A file for the next question in the AI chat (multipart).
export async function POST(request: NextRequest) {
  return proxyApiUpload("/admin/assistant/attachments", request);
}
