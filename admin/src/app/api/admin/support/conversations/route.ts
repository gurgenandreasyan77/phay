import { bffResponse } from "@/lib/bff";
import { adminApiFetch } from "@/lib/adminApi";

export async function GET() {
  const result = await adminApiFetch("/admin/support/conversations");
  return bffResponse(result);
}
