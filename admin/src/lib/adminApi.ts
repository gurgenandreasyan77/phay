// Server-only: reads the httpOnly admin session cookie and calls the
// Laravel API with a bearer token. Never import this from a "use client"
// component — the token must never reach the browser.
import { cookies } from "next/headers";
import { ADMIN_TOKEN_COOKIE } from "./adminSession";
import type {
  AdditionalServiceCategory,
  CalculatorSettingsDto,
  CarBodyType,
  ContactChannelType,
  ContaminationType,
  InteriorMaterial,
  PriceUnit,
  ServicePriceType,
  ServiceStatus,
  SoilingLevel,
} from "./api";

export { ADMIN_TOKEN_COOKIE };

const API_BASE =
  process.env.NEXT_INTERNAL_API_URL ??
  process.env.NEXT_PUBLIC_API_URL ??
  "http://localhost:8080/api/v1";

export type LeadStatus =
  | "new"
  | "contacted"
  | "waiting_for_photos"
  | "estimated"
  | "confirmed"
  | "in_progress"
  | "completed"
  | "cancelled";

export type AdminLeadDto = {
  id: number;
  name: string;
  phone: string;
  email: string | null;
  service: { id: number; slug: string; name: string } | null;
  product: { id: number; slug: string; name: string } | null;
  package: { id: number; slug: string; name: string } | null;
  propertyType: string | null;
  areaSqm: number | null;
  fulfillmentType: "delivery" | "pickup" | null;
  address: string | null;
  preferredDate: string | null;
  comment: string | null;
  preferredLocale: string | null;
  source: string | null;
  status: LeadStatus;
  carBrand: string | null;
  carModel: string | null;
  carYear: number | null;
  carBodyType: CarBodyType | null;
  seatsCount: number | null;
  interiorMaterial: InteriorMaterial | null;
  soilingLevel: SoilingLevel | null;
  contaminationTypes: ContaminationType[];
  hasOdor: boolean | null;
  odorSource: string | null;
  hasPetHair: boolean | null;
  hasChildSeat: boolean | null;
  canBringCar: boolean | null;
  preferredChannel: ContactChannelType | null;
  photos: string[];
  adminComment: string | null;
  estimatedPriceMin: number | null;
  estimatedPriceMax: number | null;
  finalPrice: number | null;
  completedAt: string | null;
  createdAt: string | null;
  updatedAt: string | null;
};

export type PaginatedLeadsDto = {
  items: AdminLeadDto[];
  meta: { currentPage: number; lastPage: number; total: number };
};

export type DashboardDto = {
  countsByStatus: Record<LeadStatus, number>;
  newToday: number;
  newThisWeek: number;
  recentLeads: AdminLeadDto[];
};

export type AnalyticsPeriod = 7 | 30 | 90 | 365;

export type AnalyticsTotalsDto = {
  orders: number;
  completed: number;
  cancelled: number;
  revenue: number;
  averageCheck: number | null;
  completedWithoutPrice: number;
  conversion: number | null;
};

export type AnalyticsDto = {
  period: { days: AnalyticsPeriod; from: string; to: string; timezone: string; currency: string };
  totals: AnalyticsTotalsDto;
  previous: AnalyticsTotalsDto;
  series: { date: string; orders: number; revenue: number }[];
  topServices: { serviceId: number | null; name: string | null; orders: number; revenue: number }[];
  sources: { source: string; orders: number; completed: number }[];
  statuses: Record<LeadStatus, number>;
  openOrders: number;
};

export type AssistantStatusDto = { configured: boolean; used: number; limit: number };

/** A person's role inside one business (see the API's BusinessRole). */
export type BusinessRole = "owner" | "admin" | "moderator";

/** Sections a business role opens: orders (+ dashboard), catalog, settings, insights (analytics + AI). */
export type BusinessPermission = "orders" | "catalog" | "settings" | "insights";

/** Servio's own people: full rights, or onboarding only (no modules, no team). */
export type PlatformRole = "super_admin" | "moderator";

export type AdminTenantDto = {
  id: number;
  name: string;
  slug: string;
  defaultLocale: string;
  status: "active" | "suspended";
  /** The signed-in user's role in this business; absent for platform staff who aren't members. */
  role?: BusinessRole;
  sitesCount?: number;
  /** Logins that can sign in to this business. */
  usersCount?: number;
  createdAt: string | null;
};

/** A login of a business, created by hand by platform staff. */
export type AdminTenantUserDto = {
  id: number;
  name: string;
  /** The login: what the person types on the sign-in page. */
  email: string;
  role?: BusinessRole;
  isSuperAdmin: boolean;
  platformRole?: PlatformRole | null;
  createdAt: string | null;
};

/** A member of Servio's platform team. */
export type AdminPlatformUserDto = {
  id: number;
  name: string;
  email: string;
  role: PlatformRole;
  createdAt: string | null;
};

export type AdminUserDto = {
  id: number;
  name: string;
  email: string;
  isSuperAdmin: boolean;
  /** Null for a business's own people. */
  platformRole: PlatformRole | null;
  /** Role in the current business (platform staff act as its owner). */
  businessRole: BusinessRole | null;
  /** What that role opens in the current business. */
  permissions: BusinessPermission[];
  /** The business the admin panel is working on right now (null if the API didn't say). */
  currentTenant: AdminTenantDto | null;
  /** Businesses the user can switch to (all of them for a super admin). */
  tenants: AdminTenantDto[];
};

export type AdminSiteDto = {
  id: number;
  name: string;
  domain: string | null;
  publicKey: string;
  allowedOrigins: string[];
  isActive: boolean;
  /** The business the site belongs to. */
  tenant?: { id: number; name: string };
  createdAt: string | null;
};

export type AdminFeatureFlagDto = {
  key: string;
  label: string;
  description: string;
  category: string;
  active: boolean;
  lastChangedAt: string | null;
  lastChangedBy: string | null;
};

export type AdminContactChannelDto = {
  id: number;
  type: ContactChannelType;
  value: string;
  label: string | null;
  isPrimary: boolean;
  sortOrder: number;
  isActive: boolean;
};

export type AdminTranslated = Partial<Record<"hy" | "ru" | "en", string>>;

export type AdminAdditionalServiceDto = {
  id: number;
  slug: string;
  category: AdditionalServiceCategory;
  name: AdminTranslated;
  note: AdminTranslated;
  alt: AdminTranslated;
  image: string;
  priceAmount: number;
  priceUnit: PriceUnit;
  discountPercent: number;
  serviceSlug: string | null;
  isActive: boolean;
  sortOrder: number;
};

export type AdminProductStatus = "draft" | "published" | "archived";

export type AdminProductImageDto = {
  id: number;
  path: string;
  alt: AdminTranslated;
  isPrimary: boolean;
  sortOrder: number;
};

export type AdminProductDto = {
  id: number;
  slug: string;
  slugHy: string;
  slugRu: string;
  slugEn: string;
  status: AdminProductStatus;

  name: AdminTranslated;
  shortDescription: AdminTranslated;
  description: AdminTranslated;
  alt: AdminTranslated;
  image: string | null;
  images: AdminProductImageDto[];

  brand: string | null;
  manufacturer: string | null;
  countryOfOrigin: string | null;
  sku: string | null;
  gtin: string | null;
  volumeValue: number | null;
  volumeUnit: string | null;
  priceAmount: number | null;
  oldPriceAmount: number | null;
  stockQuantity: number | null;
  availabilityStatus: string | null;
  sortOrder: number;
  adminNote: string | null;

  stains: AdminTranslated & Partial<Record<"hy" | "ru" | "en", string[]>>;
  surfaces: AdminTranslated & Partial<Record<"hy" | "ru" | "en", string[]>>;
  forbiddenSurfaces: AdminTranslated & Partial<Record<"hy" | "ru" | "en", string[]>>;
  phValue: number | null;
  compositionType: string | null;
  dilutionTable: Record<string, unknown>;
  usageSteps: Record<string, unknown>;
  requiresExtractor: boolean | null;
  consumptionNote: AdminTranslated;
  contactTime: AdminTranslated;
  rinseMethod: AdminTranslated;
  safetyNotes: Record<string, unknown>;
  storageConditions: AdminTranslated;
  shelfLife: AdminTranslated;
  batchTracked: boolean;
  sdsUrl: string | null;
  certificateNote: AdminTranslated;

  seoTitle: AdminTranslated;
  seoDescription: AdminTranslated;
  ogTitle: AdminTranslated;
  ogDescription: AdminTranslated;
  ogImage: string | null;
  canonicalOverride: string | null;
  seoRobots: "index" | "noindex" | null;
  primaryKeyword: string | null;
  secondaryKeywords: string[];
  seoPreview: Record<"hy" | "ru" | "en", { title: string; description: string }>;

  categoryIds: number[];
  primaryCategoryId: number | null;
  serviceIds: number[];
  relatedProductIds: number[];
  accessoryProductIds: number[];

  createdAt: string | null;
  updatedAt: string | null;
};

export type AdminProductCategoryDto = {
  id: number;
  slugHy: string;
  slugRu: string;
  slugEn: string;
  icon: string | null;
  sortOrder: number;
  name: AdminTranslated;
  shortDescription: AdminTranslated;
  productCount?: number;
  createdAt: string | null;
  updatedAt: string | null;
};

export type AdminMarketTaskPageStatus = "draft" | "published" | "archived";

export type AdminMarketTaskPageDto = {
  id: number;
  slugHy: string;
  slugRu: string;
  slugEn: string;
  status: AdminMarketTaskPageStatus;
  sortOrder: number;
  title: AdminTranslated;
  intro: AdminTranslated;
  selectionRules: Record<string, unknown>;
  precautions: Record<string, unknown>;
  seoTitle: AdminTranslated;
  seoDescription: AdminTranslated;
  relatedServiceId: number | null;
  productIds: number[];
  createdAt: string | null;
  updatedAt: string | null;
};

export type AdminSiteSettingsDto = {
  address: Partial<Record<"hy" | "ru" | "en", string>>;
  channels: AdminContactChannelDto[];
};

export type AdminServiceCategoryDto = {
  id: number;
  slug: string;
  icon: string | null;
  sortOrder: number;
  name: AdminTranslated;
  description: AdminTranslated;
  servicesCount?: number;
  createdAt: string | null;
  updatedAt: string | null;
};

export type AdminTranslatedList = Partial<Record<"hy" | "ru" | "en", string[]>>;
export type AdminFaqList = Partial<Record<"hy" | "ru" | "en", { question: string; answer: string }[]>>;

export type AdminServiceDto = {
  id: number;
  categoryId: number;
  slug: string;
  icon: string | null;
  image: string | null;
  sortOrder: number;
  status: ServiceStatus;

  name: AdminTranslated;
  shortDescription: AdminTranslated;
  description: AdminTranslated;
  whoItsFor: AdminTranslated;
  includedItems: AdminTranslatedList;
  excludedItems: AdminTranslatedList;
  processSteps: AdminTranslatedList;
  equipment: AdminTranslated;
  durationNote: AdminTranslated;
  priceFactors: AdminTranslatedList;
  faq: AdminFaqList;
  warnings: AdminTranslatedList;
  dryingTimeNote: AdminTranslated;

  carTypes: CarBodyType[];
  materials: InteriorMaterial[];
  contaminationTypes: ContaminationType[];
  priceType: ServicePriceType | null;
  priceAmount: number | null;
  priceMin: number | null;
  priceMax: number | null;
  priceByCarType: Partial<Record<CarBodyType, number>>;
  durationMinutes: number | null;

  seoTitle: AdminTranslated;
  seoDescription: AdminTranslated;
  ogTitle: AdminTranslated;
  ogDescription: AdminTranslated;
  ogImage: string | null;
  canonicalOverride: string | null;
  seoRobots: "index" | "noindex" | null;

  productIds?: number[];
  createdAt: string | null;
  updatedAt: string | null;
};

export type AdminServicePackageDto = {
  id: number;
  categoryId: number | null;
  slug: string;
  status: ServiceStatus;
  name: AdminTranslated;
  shortDescription: AdminTranslated;
  description: AdminTranslated;
  includedItems: AdminTranslatedList;
  priceType: ServicePriceType | null;
  priceAmount: number | null;
  priceMin: number | null;
  priceMax: number | null;
  priceByCarType: Partial<Record<CarBodyType, number>>;
  durationMinutes: number | null;
  image: string | null;
  sortOrder: number;
  createdAt: string | null;
  updatedAt: string | null;
};

export type AdminServiceGalleryItemDto = {
  id: number;
  serviceId: number | null;
  carType: CarBodyType | null;
  label: AdminTranslated;
  description: AdminTranslated;
  beforeImage: string;
  afterImage: string;
  altBefore: AdminTranslated;
  altAfter: AdminTranslated;
  performedAt: string | null;
  clientConsent: boolean;
  isPublished: boolean;
  sortOrder: number;
  createdAt: string | null;
};

export type AdminServiceVideoDto = {
  id: number;
  serviceId: number | null;
  title: AdminTranslated;
  videoPath: string;
  posterImage: string | null;
  sortOrder: number;
  isPublished: boolean;
  createdAt: string | null;
};

export async function getAdminToken(): Promise<string | null> {
  const store = await cookies();
  return store.get(ADMIN_TOKEN_COOKIE)?.value ?? null;
}

type AdminApiResult<T> = { ok: boolean; status: number; data: T | null };

export async function adminApiFetch<T>(
  path: string,
  init: RequestInit = {},
): Promise<AdminApiResult<T>> {
  const token = await getAdminToken();

  const response = await fetch(`${API_BASE}${path}`, {
    ...init,
    headers: {
      "Content-Type": "application/json",
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
      ...init.headers,
    },
    cache: "no-store",
  });

  const json = response.status === 204 ? null : await response.json().catch(() => null);

  return { ok: response.ok, status: response.status, data: (json?.data as T | undefined) ?? null };
}

/** Like adminApiFetch, but hands back the whole JSON body (errors included). */
export async function adminApiFetchRaw(
  path: string,
  init: RequestInit = {},
): Promise<{ status: number; json: unknown }> {
  const token = await getAdminToken();

  const response = await fetch(`${API_BASE}${path}`, {
    ...init,
    headers: {
      "Content-Type": "application/json",
      Accept: "application/json",
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
      ...init.headers,
    },
    cache: "no-store",
  });

  return { status: response.status, json: await response.json().catch(() => null) };
}

export async function getAnalytics(period: AnalyticsPeriod): Promise<AnalyticsDto | null> {
  const result = await adminApiFetch<AnalyticsDto>(`/admin/analytics?period=${period}`);
  return result.data;
}

export async function getAssistantStatus(): Promise<AssistantStatusDto | null> {
  const result = await adminApiFetch<AssistantStatusDto>("/admin/assistant");
  return result.data;
}

export async function getDashboard(): Promise<DashboardDto | null> {
  const result = await adminApiFetch<DashboardDto>("/admin/dashboard");
  return result.data;
}

/** The signed-in user, or null (no session or an expired token) — no redirect. */
export async function getMe(): Promise<AdminUserDto | null> {
  const result = await adminApiFetch<Partial<AdminUserDto>>("/admin/me");
  const me = result.data;
  if (!me) return null;
  return withMeDefaults(me);
}

/**
 * Fills fields an older API (e.g. a backend not restarted after an update)
 * doesn't send yet, instead of crashing the whole admin layout. Without
 * roles, everyone keeps what they had before: every business section.
 */
export function withMeDefaults(me: Partial<AdminUserDto>): AdminUserDto {
  return {
    ...me,
    currentTenant: me.currentTenant ?? null,
    tenants: me.tenants ?? [],
    platformRole: me.platformRole ?? (me.isSuperAdmin ? "super_admin" : null),
    businessRole: me.businessRole ?? null,
    permissions: me.permissions ?? ["orders", "catalog", "settings", "insights"],
  } as AdminUserDto;
}

export async function getPlatformUsersAdmin(): Promise<AdminPlatformUserDto[]> {
  const result = await adminApiFetch<AdminPlatformUserDto[]>("/admin/platform-users");
  return result.data ?? [];
}

export async function getTenantsAdmin(): Promise<AdminTenantDto[]> {
  const result = await adminApiFetch<AdminTenantDto[]>("/admin/tenants");
  return result.data ?? [];
}

export async function getSitesAdmin(): Promise<AdminSiteDto[]> {
  const result = await adminApiFetch<AdminSiteDto[]>("/admin/sites");
  return result.data ?? [];
}

/** Optional modules of any business (platform level, super admin only). */
export async function getTenantFeaturesAdmin(tenantId: number): Promise<AdminFeatureFlagDto[]> {
  const result = await adminApiFetch<AdminFeatureFlagDto[]>(`/admin/tenants/${tenantId}/features`);
  return result.data ?? [];
}

export async function getFeatureFlagsAdmin(): Promise<AdminFeatureFlagDto[]> {
  const result = await adminApiFetch<AdminFeatureFlagDto[]>("/admin/feature-flags");
  return result.data ?? [];
}

export async function getAdditionalServicesAdmin(): Promise<AdminAdditionalServiceDto[]> {
  const result = await adminApiFetch<AdminAdditionalServiceDto[]>("/admin/additional-services");
  return result.data ?? [];
}

export async function getProductsAdmin(): Promise<AdminProductDto[]> {
  const result = await adminApiFetch<AdminProductDto[]>("/admin/products");
  return result.data ?? [];
}

export async function getProductCategoriesAdmin(): Promise<AdminProductCategoryDto[]> {
  const result = await adminApiFetch<AdminProductCategoryDto[]>("/admin/product-categories");
  return result.data ?? [];
}

export async function getTaskPagesAdmin(): Promise<AdminMarketTaskPageDto[]> {
  const result = await adminApiFetch<AdminMarketTaskPageDto[]>("/admin/task-pages");
  return result.data ?? [];
}

export async function getCalculatorSettingsAdmin(): Promise<CalculatorSettingsDto | null> {
  const result = await adminApiFetch<CalculatorSettingsDto>("/admin/calculator-settings");
  return result.data;
}

export async function getSiteSettingsAdmin(): Promise<AdminSiteSettingsDto> {
  const result = await adminApiFetch<AdminSiteSettingsDto>("/admin/settings");
  return result.data ?? { address: {}, channels: [] };
}

export async function getServiceCategoriesAdmin(): Promise<AdminServiceCategoryDto[]> {
  const result = await adminApiFetch<AdminServiceCategoryDto[]>("/admin/service-categories");
  return result.data ?? [];
}

export async function getServicesAdmin(): Promise<AdminServiceDto[]> {
  const result = await adminApiFetch<AdminServiceDto[]>("/admin/services");
  return result.data ?? [];
}

export async function getServicePackagesAdmin(): Promise<AdminServicePackageDto[]> {
  const result = await adminApiFetch<AdminServicePackageDto[]>("/admin/service-packages");
  return result.data ?? [];
}

export async function getServiceGalleryItemsAdmin(): Promise<AdminServiceGalleryItemDto[]> {
  const result = await adminApiFetch<AdminServiceGalleryItemDto[]>("/admin/service-gallery-items");
  return result.data ?? [];
}

export async function getServiceVideosAdmin(): Promise<AdminServiceVideoDto[]> {
  const result = await adminApiFetch<AdminServiceVideoDto[]>("/admin/service-videos");
  return result.data ?? [];
}

export async function getLeads(params: {
  status?: string;
  search?: string;
  page?: number;
}): Promise<PaginatedLeadsDto> {
  const empty = { items: [], meta: { currentPage: 1, lastPage: 1, total: 0 } };

  const query = new URLSearchParams();
  if (params.status) query.set("status", params.status);
  if (params.search) query.set("search", params.search);
  if (params.page) query.set("page", String(params.page));

  const token = await getAdminToken();
  const response = await fetch(`${API_BASE}/admin/leads?${query.toString()}`, {
    headers: token ? { Authorization: `Bearer ${token}` } : {},
    cache: "no-store",
  });

  if (!response.ok) return empty;

  const json = (await response.json().catch(() => null)) as {
    data: AdminLeadDto[];
    meta?: { current_page: number; last_page: number; total: number };
  } | null;

  if (!json) return empty;

  return {
    items: json.data,
    meta: json.meta
      ? { currentPage: json.meta.current_page, lastPage: json.meta.last_page, total: json.meta.total }
      : empty.meta,
  };
}
