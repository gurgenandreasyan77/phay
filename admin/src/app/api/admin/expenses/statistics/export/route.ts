import type { NextRequest } from "next/server";
import { proxyApiFile } from "@/lib/bffFile";

// The expenses of a period (?by=&date=&format=) as a file.
export async function GET(request: NextRequest) {
  return proxyApiFile(`/admin/expenses/statistics/export${request.nextUrl.search}`);
}
