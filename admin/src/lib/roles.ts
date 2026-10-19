// Roles and what they open, shared by server and client components (no
// server-only imports here).
import type { AdminUserDto, BusinessPermission, BusinessRole, PlatformRole } from "./adminApi";

export const BUSINESS_ROLES: BusinessRole[] = ["owner", "admin", "moderator"];

export const BUSINESS_ROLE_LABELS: Record<BusinessRole, string> = {
  owner: "Սեփականատեր",
  admin: "Ադմինիստրատոր",
  moderator: "Մոդերատոր",
};

/** One line per role, shown next to the role picker. */
export const BUSINESS_ROLE_HINTS: Record<BusinessRole, string> = {
  owner: "Ամեն ինչ, ներառյալ վերլուծությունը, ծախսերը և AI օգնականը",
  admin: "Պատվերներ, ծառայություններ, մոդուլներ, կարգավորումներ (առանց եկամուտների և AI-ի)",
  moderator: "Միայն պատվերներ",
};

export const PLATFORM_ROLES: PlatformRole[] = ["super_admin", "moderator"];

export const PLATFORM_ROLE_LABELS: Record<PlatformRole, string> = {
  super_admin: "Սուպեր ադմին",
  moderator: "Պլատֆորմի մոդերատոր",
};

export const PLATFORM_ROLE_HINTS: Record<PlatformRole, string> = {
  super_admin: "Ամեն ինչ, ներառյալ մոդուլները և պլատֆորմի թիմը",
  moderator: "Բիզնեսներ, կայքեր, մուտքեր, մուտք ցանկացած բիզնես (առանց մոդուլների և թիմի)",
};

type Me = Pick<AdminUserDto, "platformRole" | "permissions"> | null | undefined;

/** Platform staff: the super admin and platform moderators. */
export function isPlatformStaff(me: Me): boolean {
  return Boolean(me?.platformRole);
}

export function isSuperAdmin(me: Me): boolean {
  return me?.platformRole === "super_admin";
}

export function can(me: Me, permission: BusinessPermission): boolean {
  return Boolean(me?.permissions?.includes(permission));
}
