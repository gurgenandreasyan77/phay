import { notFound, redirect } from "next/navigation";
import { adminApiFetch, getAdminToken, withMeDefaults, type AdminUserDto, type BusinessPermission } from "./adminApi";
import { can, isPlatformStaff, isSuperAdmin } from "./roles";

// Called at the top of every admin Server Component. Independent of the
// cheap cookie-presence check in src/proxy.ts — this actually validates the
// token against Laravel on every request.
export async function requireAdmin(): Promise<AdminUserDto> {
  const token = await getAdminToken();
  if (!token) {
    redirect("/admin/login");
  }

  const me = await adminApiFetch<Partial<AdminUserDto>>("/admin/me");
  if (!me.ok || !me.data) {
    redirect("/admin/login");
  }

  return withMeDefaults(me.data);
}

/** A business section: 404 for roles it isn't open to (the API answers 403 too). */
export async function requirePermission(permission: BusinessPermission): Promise<AdminUserDto> {
  const admin = await requireAdmin();
  if (!can(admin, permission)) notFound();
  return admin;
}

/** Onboarding screens: the super admin and platform moderators. */
export async function requirePlatformStaff(): Promise<AdminUserDto> {
  const admin = await requireAdmin();
  if (!isPlatformStaff(admin)) notFound();
  return admin;
}

/** The platform team: the super admin only. */
export async function requireSuperAdmin(): Promise<AdminUserDto> {
  const admin = await requireAdmin();
  if (!isSuperAdmin(admin)) notFound();
  return admin;
}
