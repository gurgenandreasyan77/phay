import type { NextRequest } from "next/server";
import { proxyApiFile } from "@/lib/bffFile";

// The profit report (?by=&date=&format=) as a file.
export async function GET(request: NextRequest) {
  return proxyApiFile(`/admin/analytics/finance/export${request.nextUrl.search}`);
}
