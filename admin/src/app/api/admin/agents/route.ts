import { bffResponse } from "@/lib/bff";
import { adminApiFetch } from "@/lib/adminApi";

export async function GET() {
  return bffResponse(await adminApiFetch("/admin/agents"));
}
