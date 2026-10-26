import { headers } from "next/headers";
import { redirect } from "next/navigation";
import { adminApiFetch, getAdminToken, getFeatureFlagsAdmin, withMeDefaults, type AdminUserDto, type BusinessPermission } from "./adminApi";
import { API_UNREACHABLE, ApiRequestError } from "./apiError";
import { PATH_HEADER, safeNextPath } from "./adminSession";
import { can, isOutsideBusiness, isPlatformStaff, isSuperAdmin } from "./roles";

// Called at the top of every admin Server Component. Independent of the
// cheap cookie-presence check in src/proxy.ts — this actually validates the
// token against Laravel on every request.
//
// Where a page that can't be opened sends someone (never back to itself,
// so no redirect loops):
// - no session or an expired one: sign-in, then back to this page;
// - 2FA must be set up first: /admin/setup-2fa;
// - the business's subscription ended: /admin/subscription (pages it can
//   still open pass allowLocked);
// - the account has no business or the business is suspended, a role or a
//   module that doesn't open the section: /admin/forbidden, which says why;
// - a platform page for a business's people: the business home;
// - a business page for platform staff that isn't one of its people: the
//   platform level (a business is looked at from /admin/platform/businesses/{id});
// - the API under maintenance: /admin/maintenance; down or failing: the
//   error page (error.tsx), with the reason in the server log.

/** The page being opened (set by src/proxy.ts). */
async function currentPath(): Promise<string | null> {
  return safeNextPath((await headers()).get(PATH_HEADER));
}

/** What platform staff opens outside the platform level when the business isn't theirs. */
const OPEN_OUTSIDE_BUSINESS = ["/admin/platform", "/admin/account", "/admin/forbidden", "/admin/setup-2fa", "/admin/maintenance"];

function withNext(target: string, next: string | null): string {
  return next && next !== "/admin" ? `${target}?next=${encodeURIComponent(next)}` : target;
}

export async function requireAdmin(
  options: { allowLocked?: boolean; allowTwoFactorSetup?: boolean } = {},
): Promise<AdminUserDto> {
  const token = await getAdminToken();
  if (!token) {
    redirect(withNext("/admin/login", await currentPath()));
  }

  const me = await adminApiFetch<Partial<AdminUserDto>>("/admin/me");
  if (me.status === 401) {
    // The route handler drops the dead cookie (a page can't).
    redirect(withNext("/api/admin/session/expired", await currentPath()));
  }
  if (me.status === 403) {
    redirect(`/admin/forbidden?reason=${me.error?.code === "business_suspended" ? "suspended" : "account"}`);
  }
  if (me.error?.code === "maintenance") {
    redirect("/admin/maintenance");
  }
  if (!me.ok || !me.data) {
    throw new ApiRequestError(
      me.error ?? { status: me.status, code: me.status ? "bad_response" : API_UNREACHABLE, message: "/admin/me answered without data", requestId: me.requestId },
      "GET /admin/me",
    );
  }

  const admin = withMeDefaults(me.data);
  // Platform staff and (by default) owners must set up 2FA before anything else.
  if (admin.security?.twoFactorSetupRequired && !options.allowTwoFactorSetup) {
    redirect("/admin/setup-2fa");
  }
  if (admin.subscription?.locked && !options.allowLocked) {
    redirect("/admin/subscription");
  }
  if (isOutsideBusiness(admin)) {
    const path = (await currentPath())?.split("?")[0];
    if (path && !OPEN_OUTSIDE_BUSINESS.some((open) => path === open || path.startsWith(`${open}/`))) {
      redirect("/admin/platform");
    }
  }

  return admin;
}

/** A business section: only for roles it is open to (the API answers 403 too). */
export async function requirePermission(
  permission: BusinessPermission,
  options: { allowLocked?: boolean } = {},
): Promise<AdminUserDto> {
  const admin = await requireAdmin(options);
  if (!can(admin, permission)) redirect("/admin/forbidden?reason=role");
  return admin;
}

/** A module's section (services, market, site, social): only while the module is on for the business. */
export async function requireModule(
  module: "services" | "market" | "site" | "social",
  permission: BusinessPermission,
): Promise<AdminUserDto> {
  const admin = await requirePermission(permission);
  const flags = await getFeatureFlagsAdmin();
  if (!flags.find((flag) => flag.key === module)?.active) redirect(`/admin/forbidden?reason=module&module=${module}`);
  return admin;
}

/** Onboarding screens: the super admin and platform moderators. A business's people go to their home. */
export async function requirePlatformStaff(): Promise<AdminUserDto> {
  const admin = await requireAdmin();
  if (!isPlatformStaff(admin)) redirect("/admin");
  return admin;
}

/** The platform team: the super admin only. Moderators go to the platform's home. */
export async function requireSuperAdmin(): Promise<AdminUserDto> {
  const admin = await requireAdmin();
  if (!isSuperAdmin(admin)) redirect(isPlatformStaff(admin) ? "/admin/platform" : "/admin");
  return admin;
}
