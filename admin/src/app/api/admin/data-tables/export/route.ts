import type { NextRequest } from "next/server";
import { proxyApiFile } from "@/lib/bffFile";

// Several tables as one XLSX workbook (?ids=1,2,3), streamed from the API.
export async function GET(request: NextRequest) {
  return proxyApiFile(`/admin/data-tables/export${request.nextUrl.search}`);
}
