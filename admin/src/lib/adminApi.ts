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

export type AdminTenantDto = {
  id: number;
  name: string;
  slug: string;
  defaultLocale: string;
  status: "active" | "suspended";
  /** The signed-in user's role in this business; absent for a super admin who isn't a member. */
  role?: "owner" | "editor";
  sitesCount?: number;
  createdAt: string | null;
};

export type AdminUserDto = {
  id: number;
  name: string;
  email: string;
  isSuperAdmin: boolean;
  /** The business the admin panel is working on right now. */
  currentTenant: AdminTenantDto;
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

export async function getDashboard(): Promise<DashboardDto | null> {
  const result = await adminApiFetch<DashboardDto>("/admin/dashboard");
  return result.data;
}

/** The signed-in user, or null (no session or an expired token) — no redirect. */
export async function getMe(): Promise<AdminUserDto | null> {
  const result = await adminApiFetch<AdminUserDto>("/admin/me");
  return result.data;
}

export async function getTenantsAdmin(): Promise<AdminTenantDto[]> {
  const result = await adminApiFetch<AdminTenantDto[]>("/admin/tenants");
  return result.data ?? [];
}

export async function getSitesAdmin(): Promise<AdminSiteDto[]> {
  const result = await adminApiFetch<AdminSiteDto[]>("/admin/sites");
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
