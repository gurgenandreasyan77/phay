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

/** Rows of a period and their money (null: no amount column, or a role that doesn't see money). */
export type HomeFiguresDto = { count: number; amount: number | null };

export type HomeSectionDto = {
  section: Exclude<DataTableSection, "expenses">;
  tables: number;
  rows: number;
  hasAmount: boolean;
  today: HomeFiguresDto;
  week: HomeFiguresDto;
  /** The 1st of the month to today. */
  month: HomeFiguresDto;
  /** The same days of last month. */
  previousMonth: HomeFiguresDto;
  lastAt: string | null;
};

export type HomeFinanceFiguresDto = {
  revenue: number;
  cogs: number;
  expenses: number;
  netProfit: number;
  cashLeft: number;
  margin: number | null;
};

export type HomeUpcomingItemDto = {
  kind: "row" | "site";
  id: number;
  tableId?: number;
  table?: string;
  date: string;
  time: string | null;
  title: string;
  customer: string | null;
  performer: string | null;
  amount: number | null;
  status: string | null;
};

/** The business home (GET /admin/dashboard): blocks a role or module doesn't have are null. */
export type DashboardDto = {
  today: string;
  money: boolean;
  sections: HomeSectionDto[];
  finance: (HomeFinanceFiguresDto & {
    previous: HomeFinanceFiguresDto;
    has: { revenue: boolean; cost: boolean; expenses: boolean };
  }) | null;
  upcoming: { items: HomeUpcomingItemDto[]; total: number; days: number } | null;
  site: {
    newToday: number;
    waiting: number;
    inWork: number;
    completedMonth: number;
    revenueMonth: number | null;
    recent: AdminLeadDto[];
  } | null;
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
  /** Site orders ever: none means there's no site block to show. */
  everOrders: number;
};

/** A business's own table (orders, sales, expenses): see the API's DataTableController. */
export type DataTableSection = "orders" | "sales" | "expenses";

export type DataColumnType = "text" | "number" | "money" | "date" | "datetime" | "select" | "boolean";

/** What a column means, whatever it's called (see the API's ColumnRole). */
export type ColumnRole =
  | "date"
  | "item"
  | "quantity"
  | "unit"
  | "unit_price"
  | "amount"
  | "cost"
  | "payment"
  | "channel"
  | "status"
  | "category"
  | "performer"
  | "customer"
  | "phone"
  | "address"
  | "note";

export type AdminDataColumnDto = {
  /** Stable key the rows' values are stored under; absent for a column not saved yet. */
  key?: string;
  label: string;
  type: DataColumnType;
  required: boolean;
  options: string[];
  role?: ColumnRole | null;
};

/** What a table is kept for: a day, ISO week, month, quarter, year, or any dates (custom). */
export type TablePeriod = "day" | "week" | "month" | "quarter" | "year" | "custom";

/** A period of time: "2026-W47", "47-րդ շաբաթ 2026 (16.11–22.11)", 2026-11-16 … 2026-11-22. */
export type PeriodDto = {
  kind: Exclude<TablePeriod, "custom">;
  key: string;
  label: string;
  start: string;
  end: string;
};

/** Where a row is filed: its year, quarter, month, ISO week (of weekYear) and day. */
export type PeriodTagsDto = { year: number; quarter: number; month: number; week: number; weekYear: number; day: string };

export type AdminDataTableDto = {
  id: number;
  section: DataTableSection;
  name: string;
  description: string | null;
  columns: Required<AdminDataColumnDto>[];
  /** The chosen period; null for a table made before periods (its month may be inferred). */
  period: TablePeriod | null;
  /** The period the table holds, chosen or (inferred) read from its name or rows; null for any dates. */
  periodInfo: (PeriodDto & { inferred: boolean }) | null;
  /** Keys of the columns (found by role) a row's date and money come from. */
  dateColumn: string | null;
  amountColumn: string | null;
  /** How a row's money is counted, e.g. "Գին × Քանակ". */
  amountLabel: string | null;
  rowsCount?: number;
  /** In the list only: rows with no date, first and last date ("Y-m-d H:i"), money of all rows, last import. */
  rowsWithoutDate?: number;
  firstAt?: string | null;
  lastAt?: string | null;
  amountTotal?: number | null;
  lastImportAt?: string | null;
  createdAt: string | null;
  updatedAt: string | null;
};

export type DataCellValue = string | number | boolean | null;

export type AdminDataTableRowDto = {
  id: number;
  values: Record<string, DataCellValue>;
  source: "manual" | "import";
  period: PeriodTagsDto | null;
  employeeId: number | null;
  createdAt: string | null;
  updatedAt: string | null;
};

export type PaginatedDataRowsDto = {
  items: AdminDataTableRowDto[];
  meta: { currentPage: number; lastPage: number; total: number };
};

/** An uploaded file and its import into a table (see the API's DataImportController). */
export type AdminDataImportDto = {
  id: number;
  tableId: number | null;
  fileName: string;
  format: "csv" | "xlsx" | "xml";
  headers: string[];
  totalRows: number;
  status: "uploaded" | "done" | "undone";
  importedRows: number;
  skippedRows: number;
  /** Skipped rows (the first 200): the file's line and, per table column, why. */
  errors: { line: number; errors: Record<string, string> }[];
  user: string | null;
  createdAt: string | null;
};

/** Right after the upload: what the file holds and how it maps to the table. */
export type AdminDataImportPreviewDto = AdminDataImportDto & {
  sampleRows: string[][];
  truncated: boolean;
  maxRows: number;
  /** Table column key → file column index; null when no table was given. */
  mapping: Record<string, number | null> | null;
  /** Columns for a new table made from this file. */
  guessedColumns: AdminDataColumnDto[];
  /** The table's date column: without it in the file, one date is given for all rows. */
  dateColumn?: { key: string; label: string } | null;
  /** A file for a new table: tables whose columns it matches (a daily file goes to the same table). */
  matchingTables?: { id: number; name: string; matched: number; columns: number }[];
};

/** Figures of all tables of a section together (see the API's TableAnalytics). */
export type DashboardWidgetId =
  | "revenue"
  | "profit"
  | "margin"
  | "count"
  | "perDay"
  | "perMonth"
  | "averageCheck"
  | "units"
  | "revenueChart"
  | "countChart"
  | "profitChart"
  | "weekdays"
  | "hours"
  | "peaks"
  | "months"
  | "byItem"
  | "byPayment"
  | "byChannel"
  | "byStatus"
  | "byOwnColumns"
  | "ownNumbers"
  | "tables";

export type TableMetricDto = {
  key: "revenue" | "profit" | "margin" | "count" | "perDay" | "perMonth" | "averageCheck" | "quantity" | "averageQuantity" | "unitPrice";
  label: string;
  format: "money" | "count" | "number" | "percent";
  value: number | null;
  previous: number | null;
  unit?: string | null;
  note?: string | null;
};

export type TableAnalyticsDto = {
  section: DataTableSection;
  period: { days: AnalyticsPeriod; from: string; to: string };
  rows: { total: number; withoutDate: number; current: number; previous: number };
  amount: { current: number; previous: number; average: number | null } | null;
  /** `profit` only when a table has a unit cost column. */
  series: { date: string; rows: number; amount: number; profit?: number }[];
  /** The figures this business's columns allow (no price column, no revenue...). */
  metrics: TableMetricDto[];
  /** Best, weakest (finished) and average day or month, by money or by count. */
  peaks: {
    by: "amount" | "rows";
    bucket: "day" | "month";
    best: { date: string; rows: number; amount: number };
    worst: { date: string; rows: number; amount: number } | null;
    average: number | null;
    buckets: number;
  } | null;
  /** The last twelve months from the business's first one, with their peaks. */
  months: { series: { date: string; rows: number; amount: number }[]; peaks: TableAnalyticsDto["peaks"] } | null;
  /** Rows by day of the week, 1 Monday … 7 Sunday. */
  weekdays: { day: number; rows: number; amount: number }[];
  /** Rows by hour, for tables whose date has a time. */
  hours: { hour: number; rows: number }[];
  /** The owner's choice of widgets and the catalog to choose from. */
  dashboard: {
    saved: boolean;
    enabled: DashboardWidgetId[];
    widgets: { id: DashboardWidgetId; group: "figures" | "charts" | "tables"; missing: string[] }[];
  };
  breakdowns: {
    label: string;
    /** The column's role; null for the business's own column. */
    role: ColumnRole | null;
    distinct: number;
    values: { value: string; rows: number; amount: number | null }[];
  }[];
  /** Sums of the business's own number columns (quantities are in metrics). */
  numbers: { label: string; unit: string | null; sum: number; filled: number }[];
  tables: {
    id: number;
    name: string;
    dateColumn: string | null;
    amountColumn: string | null;
    rows: number;
    amount: number | null;
    total: number;
    withoutDate: number;
    firstAt: string | null;
    lastAt: string | null;
  }[];
  /** Orders only: the tables' orders and the site's together. */
  withSite?: {
    orders: number;
    previousOrders: number;
    revenue: number;
    previousRevenue: number;
    series: { date: string; rows: number; amount: number }[];
  };
};

export type AssistantStatusDto = { configured: boolean; used: number; limit: number };

/** A person's role inside one business (see the API's BusinessRole). */
export type BusinessRole = "owner" | "admin" | "moderator";

/** Sections a business role opens: orders (+ dashboard), catalog, settings, insights (analytics + AI). */
export type BusinessPermission = "orders" | "catalog" | "settings" | "insights" | "expenses";

/** Servio's own people: full rights, or onboarding only (no modules, no team). */
export type PlatformRole = "super_admin" | "moderator";

export type AdminTenantDto = {
  id: number;
  name: string;
  /** Public URL of the logo the business uploaded in its settings. */
  logo?: string | null;
  slug: string;
  defaultLocale: string;
  status: "active" | "suspended";
  /** The signed-in user's role in this business; absent for platform staff who aren't members. */
  role?: BusinessRole;
  sitesCount?: number;
  /** Logins that can sign in to this business. */
  usersCount?: number;
  /** Platform list only: the address and active contacts from the business's settings. */
  address?: Partial<Record<"hy" | "ru" | "en", string>>;
  contacts?: { type: ContactChannelType; value: string; label: string | null }[];
  /** Platform list only: the business's chat messages the platform hasn't read. */
  unreadMessages?: number;
  createdAt: string | null;
};

/** A chat message between a business and the platform team. */
export type SupportMessageDto = {
  id: number;
  body: string;
  /** Written by the platform team (true) or by the business (false). */
  fromPlatform: boolean;
  authorName: string | null;
  readAt: string | null;
  createdAt: string | null;
};

/** One business's conversation in the platform's list of chats. */
export type SupportConversationDto = {
  tenant: { id: number; name: string; logo: string | null };
  lastMessage: SupportMessageDto;
  unread: number;
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
  /** The current business's subscription (absent from an older API). */
  subscription?: SubscriptionSummaryDto | null;
};

/* ---------------------------------------------------------- Subscriptions */

export type SubscriptionStatus = "trial" | "active" | "free" | "canceling" | "expired" | "unlimited";

/** What the admin layout needs: a banner before the end, a lock after it. */
export type SubscriptionSummaryDto = {
  status: SubscriptionStatus;
  statusLabel: string;
  endsAt: string | null;
  daysLeft: number | null;
  /** Within the warning window (10 days by default). */
  ending: boolean;
  /** Ended: the business's people can open only the subscription, the chat and their account. */
  locked: boolean;
};

export type SubscriptionModulePriceDto = {
  key: string;
  label: string;
  price: number;
  /** On for this business. */
  active: boolean;
  /** Its price is inside the plan. */
  included: boolean;
};

export type SubscriptionDto = {
  status: SubscriptionStatus;
  statusLabel: string;
  plan: { id: number; name: string; price: number } | null;
  unlimited: boolean;
  trialEndsAt: string | null;
  paidUntil: string | null;
  freeUntil: string | null;
  /** The super admin turned it off from this moment. */
  cancelAt: string | null;
  endsAt: string | null;
  daysLeft: number | null;
  ending: boolean;
  warnDays: number;
  /** AMD a month: the plan plus the modules on that it doesn't include. */
  pricing: { plan: number; modules: SubscriptionModulePriceDto[]; total: number };
};

export type SubscriptionPaymentDto = {
  id: number;
  amount: number;
  months: number;
  periodFrom: string | null;
  periodTo: string | null;
  note: string | null;
  recordedBy?: string | null;
  createdAt: string | null;
};

export type PlanDto = {
  id: number;
  name: string;
  description: string | null;
  price: number;
  trialDays: number;
  includedModules: string[];
  isDefault: boolean;
  isActive: boolean;
  sortOrder: number;
  subscriptionsCount?: number;
};

export type ModulePriceDto = { key: string; label: string; description: string; price: number };

/** The business's own subscription page. */
export type BusinessSubscriptionDto = {
  subscription: SubscriptionDto;
  payments: SubscriptionPaymentDto[];
  plans: { id: number; name: string; description: string | null; price: number; includedModules: string[] }[];
};

export type SubscriptionTenantDto = { id: number; name: string; logo: string | null; status: "active" | "suspended" };

export type PlatformSubscriptionRowDto = {
  tenant: SubscriptionTenantDto;
  subscription: SubscriptionDto;
  lastPayment: SubscriptionPaymentDto | null;
};

export type TenantSubscriptionDetailDto = {
  tenant: SubscriptionTenantDto;
  subscription: SubscriptionDto;
  payments: SubscriptionPaymentDto[];
  plans: { id: number; name: string; price: number; isActive: boolean }[];
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
  /** Older APIs don't send it. */
  business?: { name: string; logo: string | null };
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

export async function getTableAnalytics(period: AnalyticsPeriod): Promise<TableAnalyticsDto[]> {
  const result = await adminApiFetch<TableAnalyticsDto[]>(`/admin/analytics/tables?period=${period}`);
  return result.data ?? [];
}

/** The statistics screen: every figure of each section, whatever the analytics dashboard shows. */
export type TableStatisticsDto = Omit<TableAnalyticsDto, "dashboard">;

export async function getTableStatistics(period: AnalyticsPeriod): Promise<TableStatisticsDto[]> {
  const result = await adminApiFetch<TableStatisticsDto[]>(`/admin/analytics/statistics?period=${period}`);
  return result.data ?? [];
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

export async function getTenantsAdmin(search?: string): Promise<AdminTenantDto[]> {
  const query = search ? `?search=${encodeURIComponent(search)}` : "";
  const result = await adminApiFetch<AdminTenantDto[]>(`/admin/tenants${query}`);
  return result.data ?? [];
}

/** The platform's chats: businesses that have written or been written to. */
export async function getSupportConversations(): Promise<SupportConversationDto[]> {
  const result = await adminApiFetch<SupportConversationDto[]>("/admin/support/conversations");
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

export async function getDataTables(section?: DataTableSection): Promise<AdminDataTableDto[]> {
  const result = await adminApiFetch<AdminDataTableDto[]>(`/admin/data-tables${section ? `?section=${section}` : ""}`);
  return result.data ?? [];
}

/** A row of an order table on a day of the calendar (see the API's CalendarController). */
export type CalendarRowDto = {
  id: number;
  tableId: number;
  /** "Y-m-d", the business's local day. */
  date: string;
  /** "H:i" for a date-and-time column; null for a date column (the whole day). */
  time: string | null;
  amount: number | null;
  values: Record<string, DataCellValue>;
};

/** An order from the site on the day the customer asked for. */
export type CalendarLeadDto = {
  id: number;
  date: string;
  name: string;
  phone: string;
  service: string | null;
  status: LeadStatus;
  finalPrice: number | null;
};

export type CalendarDto = {
  from: string;
  to: string;
  /** The order tables with a date column: where rows can be added from the calendar. */
  tables: AdminDataTableDto[];
  /** The period of each table kept per day, week, month, quarter or year, by table id; other tables aren't listed. */
  periods: Record<number, PeriodDto>;
  rows: CalendarRowDto[];
  /** More rows than the API sends at once. */
  truncated: boolean;
  leads: CalendarLeadDto[];
};

export async function getCalendar(from: string, to: string): Promise<CalendarDto | null> {
  const result = await adminApiFetch<CalendarDto>(`/admin/calendar?from=${from}&to=${to}`);
  return result.data;
}

export async function getDataTable(id: number): Promise<AdminDataTableDto | null> {
  const result = await adminApiFetch<AdminDataTableDto>(`/admin/data-tables/${id}`);
  return result.data;
}

export async function getDataTableRows(
  id: number,
  params: { search?: string; page?: number; period?: PeriodKind; date?: string },
): Promise<PaginatedDataRowsDto> {
  const empty = { items: [], meta: { currentPage: 1, lastPage: 1, total: 0 } };

  const query = new URLSearchParams();
  if (params.search) query.set("search", params.search);
  if (params.page) query.set("page", String(params.page));
  if (params.period && params.date) {
    query.set("period", params.period);
    query.set("date", params.date);
  }

  const result = await adminApiFetchRaw(`/admin/data-tables/${id}/rows?${query.toString()}`);
  if (result.status !== 200 || !result.json) return empty;

  const json = result.json as {
    data: AdminDataTableRowDto[];
    meta?: { current_page: number; last_page: number; total: number };
  };

  return {
    items: json.data,
    meta: json.meta
      ? { currentPage: json.meta.current_page, lastPage: json.meta.last_page, total: json.meta.total }
      : empty.meta,
  };
}

export type PeriodKind = PeriodDto["kind"];

/** Someone who does the business's orders (see the API's EmployeeController). */
export type AdminEmployeeDto = {
  id: number;
  name: string;
  position: string | null;
  phone: string | null;
  isActive: boolean;
  /** The group they work in, with its manager. */
  group: { id: number; name: string; manager: { id: number; name: string } | null } | null;
  /** The groups they lead: a manager when not empty. */
  managedGroups: { id: number; name: string }[];
  /** Rows linked to them, of any period. */
  ordersCount: number | null;
  createdAt: string | null;
};

/** A team of employees led by a manager (an employee who may lead several groups). */
export type AdminEmployeeGroupDto = {
  id: number;
  name: string;
  manager: { id: number; name: string } | null;
  employeesCount: number | null;
};

/** Orders and money of a group of rows: revenue is the amount, costs are unit cost × quantity. */
export type EmployeeFiguresDto = {
  orders: number;
  revenue: number | null;
  costs: number | null;
  profit: number | null;
  averageCheck: number | null;
};

export type EmployeeStatsDto = {
  period: PeriodDto | null;
  hasPerformerColumn: boolean;
  hasAmount: boolean;
  hasCost: boolean;
  total: EmployeeFiguresDto;
  employees: (EmployeeFiguresDto & { id: number; name: string; position: string | null; isActive: boolean; groupId: number | null })[];
  /** Each group's employees together. */
  groups: (EmployeeFiguresDto & { id: number; name: string; manager: string | null })[];
  /** Rows with no performer. */
  unassigned: EmployeeFiguresDto;
  /** Names in the performer column that aren't employees: outside performers, or names from a file. */
  unknown: (EmployeeFiguresDto & { name: string })[];
};

export type EmployeeRowDto = {
  id: number;
  table: { id: number; name: string; section: DataTableSection };
  occurredAt: string | null;
  item: string | null;
  status: string | null;
  amount: number | null;
  cost: number | null;
};

export type EmployeeDetailDto = {
  employee: AdminEmployeeDto;
  period: PeriodDto | null;
  figures: EmployeeFiguresDto;
  rows: EmployeeRowDto[];
  meta: { currentPage: number; lastPage: number; total: number };
};

export async function getEmployees(): Promise<AdminEmployeeDto[]> {
  const result = await adminApiFetch<AdminEmployeeDto[]>("/admin/employees");
  return result.data ?? [];
}

export async function getEmployeeGroups(): Promise<AdminEmployeeGroupDto[]> {
  const result = await adminApiFetch<AdminEmployeeGroupDto[]>("/admin/employee-groups");
  return result.data ?? [];
}

function periodQuery(params: { period?: PeriodKind; date?: string; page?: number }): string {
  const query = new URLSearchParams();
  if (params.period) query.set("period", params.period);
  if (params.period && params.date) query.set("date", params.date);
  if (params.page) query.set("page", String(params.page));
  return query.toString();
}

export async function getEmployeeStats(params: {
  period?: PeriodKind;
  date?: string;
  section?: DataTableSection;
}): Promise<EmployeeStatsDto | null> {
  const section = params.section ? `&section=${params.section}` : "";
  const result = await adminApiFetch<EmployeeStatsDto>(`/admin/employees/stats?${periodQuery(params)}${section}`);
  return result.data;
}

export async function getEmployee(
  id: number,
  params: { period?: PeriodKind; date?: string; page?: number },
): Promise<EmployeeDetailDto | null> {
  const result = await adminApiFetchRaw(`/admin/employees/${id}?${periodQuery(params)}`);
  if (result.status !== 200 || !result.json) return null;
  const json = result.json as { data: Omit<EmployeeDetailDto, "meta">; meta: EmployeeDetailDto["meta"] };
  return { ...json.data, meta: json.meta };
}

/** A kind of the business's expenses (see the API's ExpenseCategories). */
export type ExpenseCategoryDto = {
  id: number;
  /** Set for a ready-made one (taxes, rent...): it can be renamed, not deleted. */
  key: string | null;
  name: string;
  /** Whether the net profit subtracts it. */
  inProfit: boolean;
  isDefault: boolean;
  recurringCount?: number;
};

export type RecurringFrequency = "week" | "month" | "quarter" | "year";

export type RecurringExpenseDto = {
  id: number;
  name: string;
  category: { id: number; name: string; inProfit: boolean } | null;
  amount: number;
  /** About how much a month, to compare. */
  monthly: number;
  frequency: RecurringFrequency;
  startsOn: string;
  endsOn: string | null;
  note: string | null;
};

/** Money in and out of one period (see the API's Finance). */
export type FinancePeriodDto = {
  kind: PeriodKind;
  key: string;
  label: string;
  start: string;
  end: string;
  revenue: number;
  /** Cost of goods: unit cost × quantity. */
  cogs: number;
  grossProfit: number;
  /** Expenses counted in the profit. */
  expenses: number;
  /** Expenses kept out of the profit (equipment, loans, the owner's withdrawals). */
  excluded: number;
  expenseRows: number;
  netProfit: number;
  cashLeft: number;
  margin: number | null;
};

export type FinanceCategoryDto = {
  id: number | null;
  key: string | null;
  name: string;
  inProfit: boolean;
  amount: number;
  recurring: number;
  count: number;
};

export type FinanceDto = {
  by: PeriodKind;
  today: string;
  periods: FinancePeriodDto[];
  current: FinancePeriodDto & { previous: FinancePeriodDto | null; categories: FinanceCategoryDto[] };
  total: Omit<FinancePeriodDto, "kind" | "key" | "label" | "start" | "end">;
  categories: FinanceCategoryDto[];
  has: { revenue: boolean; cost: boolean; expenses: boolean };
  notes: string[];
};

export async function getExpenseCategories(): Promise<ExpenseCategoryDto[]> {
  const result = await adminApiFetch<ExpenseCategoryDto[]>("/admin/expense-categories");
  return result.data ?? [];
}

export async function getRecurringExpenses(): Promise<RecurringExpenseDto[]> {
  const result = await adminApiFetch<RecurringExpenseDto[]>("/admin/recurring-expenses");
  return result.data ?? [];
}

export async function getFinance(params: { by?: PeriodKind; date?: string }): Promise<FinanceDto | null> {
  const query = new URLSearchParams();
  if (params.by) query.set("by", params.by);
  if (params.date) query.set("date", params.date);
  const result = await adminApiFetch<FinanceDto>(`/admin/analytics/finance?${query}`);
  return result.data;
}

export async function getDataImports(tableId: number): Promise<AdminDataImportDto[]> {
  const result = await adminApiFetch<AdminDataImportDto[]>(`/admin/data-tables/${tableId}/imports`);
  return result.data ?? [];
}

export async function getDataImport(id: number, tableId: number): Promise<AdminDataImportPreviewDto | null> {
  const result = await adminApiFetch<AdminDataImportPreviewDto>(`/admin/data-imports/${id}?data_table_id=${tableId}`);
  return result.data;
}

export async function getSubscription(): Promise<BusinessSubscriptionDto | null> {
  const result = await adminApiFetch<BusinessSubscriptionDto>("/admin/subscription");
  return result.data;
}

export async function getPlansAdmin(): Promise<PlanDto[]> {
  const result = await adminApiFetch<PlanDto[]>("/admin/plans");
  return result.data ?? [];
}

export async function getModulePricesAdmin(): Promise<ModulePriceDto[]> {
  const result = await adminApiFetch<ModulePriceDto[]>("/admin/module-prices");
  return result.data ?? [];
}

export async function getSubscriptionsAdmin(status?: SubscriptionStatus): Promise<PlatformSubscriptionRowDto[]> {
  const result = await adminApiFetch<PlatformSubscriptionRowDto[]>(`/admin/subscriptions${status ? `?status=${status}` : ""}`);
  return result.data ?? [];
}

export async function getTenantSubscriptionAdmin(tenantId: number): Promise<TenantSubscriptionDetailDto | null> {
  const result = await adminApiFetch<TenantSubscriptionDetailDto>(`/admin/tenants/${tenantId}/subscription`);
  return result.data;
}
