import { redirect } from "next/navigation";
import { adminApiFetch, getAdminToken, type AdminUserDto } from "./adminApi";

// Called at the top of every admin Server Component. Independent of the
// cheap cookie-presence check in src/proxy.ts — this actually validates the
// token against Laravel on every request.
export async function requireAdmin(): Promise<AdminUserDto> {
  const token = await getAdminToken();
  if (!token) {
    redirect("/admin/login");
  }

  const me = await adminApiFetch<AdminUserDto>("/admin/me");
  if (!me.ok || !me.data) {
    redirect("/admin/login");
  }

  return me.data;
}
