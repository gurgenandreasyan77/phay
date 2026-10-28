// Server-only: reads the httpOnly admin session cookie and calls the
// Laravel API with a bearer token. Never import this from a "use client"
// component — the token must never reach the browser.
import { cookies } from "next/headers";
import { ADMIN_TOKEN_COOKIE } from "./adminSession";
import type { ApiErrorInfo } from "./apiError";
import { requestApi } from "./apiTransport";
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
  | "time"
  | "duration"
  | "item"
  | "category"
  | "model"
  | "brand"
  | "sku"
  | "country"
  | "material"
  | "color"
  | "size"
  | "quantity"
  | "unit"
  | "unit_price"
  | "amount"
  | "discount"
  | "discount_percent"
  | "cost"
  | "commission"
  | "delivery_fee"
  | "tax"
  | "payment"
  | "channel"
  | "status"
  | "delivery_method"
  | "performer"
  | "intermediary"
  | "supplier"
  | "city"
  | "branch"
  | "project"
  | "order_number"
  | "gender"
  | "age"
  | "customer"
  | "phone"
  | "email"
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
  /** What it was made from: null (by hand or a file), "google" (the Google calendars). */
  source: "google" | null;
  /** An older version replaced by a newer one: kept, but left out of the calendar and analytics. */
  archivedAt: string | null;
  replacedById: number | null;
  /** Archived by splitting or joining tables: the reshape (it can be undone). */
  reshapeId: number | null;
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

/** Rows without a date when tables are split or joined: a table of their own, or the newest table. */
export type ReshapeUndated = "separate" | "latest";

/** What splitting or joining tables makes (the API's TableReshaper preview). */
export type TableReshapePreviewDto = {
  section: DataTableSection;
  period: TablePeriod;
  periodLabel: string;
  undated: ReshapeUndated;
  sources: { id: number; name: string; period: string | null }[];
  targets: {
    key: string;
    name: string;
    period: PeriodDto | null;
    rows: number;
    amount: number;
    firstAt: string | null;
    lastAt: string | null;
    /** The table of the rows without a date. */
    undated: boolean;
  }[];
  rows: number;
  undatedRows: number;
  columns: string[];
  /** Columns some of the tables had and the first one didn't. */
  addedColumns: string[];
  /** Other tables already kept for one of these periods. */
  conflicts: { id: number; name: string; period: string | null }[];
};

/** A reshape: planned (by an AI agent, the preview as things are now), applied or undone. */
export type TableReshapeDto = {
  id: number;
  status: "planned" | "applied" | "undone";
  appliedAt: string | null;
  undoneAt: string | null;
  section: DataTableSection;
  period: TablePeriod;
  periodLabel: string;
  /** Planned but no longer possible (a table was deleted...), and why. */
  stale?: string | null;
  /** Applied or undone: the tables that went in and came out. */
  results?: { id: number; name: string; rows: number; archived: boolean }[];
  /** Applied: why it can't be undone any more, null when it can. */
  undoBlocked?: string | null;
} & Partial<Omit<TableReshapePreviewDto, "sources" | "section" | "period" | "periodLabel">> & {
    sources?: { id: number; name: string; period?: string | null; rows?: number; archived?: boolean }[];
  };

/** A card under an answer of the AI assistant: a reshape to apply, or a file to download. */
/** A change of the tables an AI agent prepared (the API's TableChanges), shown as a card in the chat. */
export type TableChangeDto = {
  id: number;
  kind: "create_table" | "edit_table" | "delete_tables" | "add_rows" | "update_rows" | "delete_rows";
  status: "planned" | "applied" | "undone" | "discarded";
  section: DataTableSection;
  /** «Ավելացնել 2 տող «Վաճառքներ»-ում» */
  title: string;
  table: { id: number; name: string; archived: boolean } | null;
  tables: { id: number; name: string; rows: number }[] | null;
  /** What changes in the table itself, a line each. */
  changes: string[];
  /** While planned: performers away on their row's day. */
  warnings: string[];
  columns: { key: string; label: string; type: AdminDataColumnDto["type"] }[];
  /** Rows added, edited (with the old values and the changed keys) or deleted; the first ones. */
  rows: { id?: number; values: Record<string, DataCellValue>; before?: Record<string, DataCellValue>; changed?: string[] }[];
  rowsTotal: number;
  /** Planned but the tables changed since, and why. */
  stale: string | null;
  undoable: boolean;
  /** Applied: why it can't be undone any more, null when it can. */
  undoBlocked: string | null;
  appliedAt: string | null;
  undoneAt: string | null;
};

export type AssistantActionDto =
  | { type: "reshape"; id: number }
  | { type: "change"; id: number }
  | { type: "hr"; id: number }
  /** A teammate the agent asked while answering, with the question and what they said. */
  | { type: "consult"; agent: string; name: string; role: string; question: string; answer?: string }
  /** Work handed to a teammate in the background, with its report; what it made is under the answer. */
  | { type: "delegate"; agent: string; name: string; role: string; task: string; answer?: string }
  /** Another agent's work: a button to its chat, or to request it when the business doesn't have it. */
  | { type: "refer"; agent: string; name: string; role: string; task?: string }
  | {
      type: "download";
      kind: "tables" | "finance" | "expenses";
      ids?: number[];
      by?: PeriodKind;
      date?: string;
      format: "xlsx" | "csv";
      label: string;
    };

export type AdminDataTableRowDto = {
  id: number;
  values: Record<string, DataCellValue>;
  source: "manual" | "import" | "google";
  period: PeriodTagsDto | null;
  employeeId: number | null;
  /** A copy of that row of another table: counted once, there. */
  duplicateOf?: number | null;
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
  | "deductions"
  | "duration"
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
  | "byDetails"
  | "byOwnColumns"
  | "ownNumbers"
  | "tables";

export type TableMetricDto = {
  key:
    | "revenue"
    | "profit"
    | "margin"
    | "count"
    | "perDay"
    | "perMonth"
    | "averageCheck"
    | "quantity"
    | "averageQuantity"
    | "unitPrice"
    | "discounts"
    | "commission"
    | "deliveryFees"
    | "tax"
    | "averageDuration"
    | "totalDuration";
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

/** AI assistant requests of this subscription month (renews on the day the business paid, not on the 1st). */
export type AssistantUsageDto = {
  used: number;
  /** This month's plan requests plus bonus ones; null: no limit. */
  limit: number | null;
  planLimit: number | null;
  /** Bonus requests left (they don't expire). */
  bonus: number;
  remaining: number | null;
  unlimited: boolean;
  periodStart: string;
  resetsAt: string;
};

/** A plan with more requests, offered when they run out. */
export type AssistantPlanOfferDto = { id: number; name: string; price: number; assistantRequests: number | null };

export type AssistantStatusDto = AssistantUsageDto & {
  configured: boolean;
  offer: { plans: AssistantPlanOfferDto[] };
};

/** One kept chat with the assistant, named by its first question. */
export type AssistantConversationDto = {
  id: number;
  title: string;
  /** The AI agent it is with (AgentDto.key); null: the assistant. */
  agent?: string | null;
  /** Questions and answers together. */
  messages: number;
  createdAt: string | null;
  updatedAt: string | null;
};

/** A file attached to a question in the AI chat; read through /api/admin/assistant/attachments/{id}/file. */
export type AssistantAttachmentDto = {
  id: number;
  kind: "image" | "pdf" | "text" | "video" | "sheet" | "document";
  name: string;
  mime: string;
  size: number;
  width: number | null;
  height: number | null;
  /** upload: the person attached it; design: an AI agent made it (a PNG of its answer); video: an MP4 an agent cut; document: a Word file (or a ZIP of many) an agent made. */
  origin?: "upload" | "design" | "video" | "document";
  /** A design's name and size (post, story, square, landscape, flyer), or a video's (reel, feed, square, landscape). */
  title?: string | null;
  format?: string | null;
  /** A video's length in seconds. */
  duration?: number | null;
  /** A ZIP's (or a print file's) number of documents. */
  documents?: number | null;
};

/** A video an AI agent cuts from the owner's clips; asked about at /api/admin/assistant/videos/{id} until it is ready. */
export type AssistantVideoDto = {
  id: number;
  status: "rendering" | "done" | "failed";
  title: string;
  format: string;
  duration: number;
  error: string | null;
  /** The MP4, once done. */
  attachment: AssistantAttachmentDto | null;
  /** 0-100 while rendering (from the polling endpoint). */
  progress?: number | null;
};

export type AssistantMessageDto = {
  id: number;
  role: "user" | "assistant";
  content: string;
  refused: boolean;
  /** Files of a question, or the pictures and documents an agent made in an answer. */
  attachments?: AssistantAttachmentDto[];
  /** Cards under an answer: a reshape to apply, a file to download. */
  actions?: AssistantActionDto[];
  /** Videos an agent cut in an answer. */
  videos?: AssistantVideoDto[];
  createdAt: string | null;
};

export type AssistantConversationDetailDto = Omit<AssistantConversationDto, "messages"> & {
  messages: AssistantMessageDto[];
};

/** One of the person's latest chats, for coming back to it (header, assistant page). */
export type RecentChatDto = {
  id: number;
  title: string;
  /** The AI agent (AgentDto.key); null: the assistant. */
  agent: string | null;
  /** «Արամ» and «Վերլուծաբան», or the assistant's. */
  name: string;
  role: string;
  /** The last question and the opening words of the last answer, plain. */
  question: string | null;
  answer: string | null;
  /** An answer came after the person last saw the chat. */
  unread: boolean;
  answeredAt: string | null;
  /** The later of the last answer and of the last time it was opened. */
  activeAt: string | null;
};

/** A question being answered right now (a new chat has no id yet). */
export type AskingChatDto = {
  agent: string | null;
  name: string;
  role: string;
  conversationId: number | null;
  question: string;
  startedAt: string;
};

export type RecentChatsDto = {
  chats: RecentChatDto[];
  asking: AskingChatDto[];
  /** Chats with an answer not seen yet. */
  unread: number;
};

/** One AI agent of the platform, with what this business has of it. */
export type AgentDto = {
  key: string;
  persona: { hy: string; ru: string; en: string };
  role: { hy: string; ru: string; en: string };
  /** «Անի · Հաշվապահ» */
  label: string;
  tagline: string;
  description: string;
  skills: string[];
  /** What it can't do yet (e.g. doesn't make images itself). */
  limitation: string | null;
  greeting: string;
  suggestions: string[];
  /** It asks for the brand profile before its first piece of work. */
  needsBrand: boolean;
  /** On the business's team. */
  active: boolean;
  activatedAt: string | null;
  /** AMD a month. */
  price: number;
  /** Its price is inside the business's plan. */
  included: boolean;
  /** What it was told to remember about the business. */
  notes: string | null;
};

export type BrandColorDto = { hex: string; name: string | null };

/** What every agent knows about the business. */
export type BrandProfileDto = {
  about: string | null;
  offer: string | null;
  audience: string | null;
  tone: string | null;
  visualStyle: string | null;
  colors: BrandColorDto[];
  examples: string | null;
  avoid: string | null;
  complete: boolean;
  filled: number;
  total: number;
  updatedAt: string | null;
};

/** A picture of the business the agents look at; read through /api/admin/agents/brand-assets/{id}/file. */
export type BrandAssetDto = {
  id: number;
  kind: "logo" | "example";
  name: string;
  mime: string;
  size: number;
  note: string | null;
  createdAt: string | null;
};

export type AgentTeamDto = {
  agents: AgentDto[];
  profile: BrandProfileDto;
  /** The logo first, then the examples. */
  assets: BrandAssetDto[];
  /** May chat with the agents (the owner). */
  canChat: boolean;
  /** May ask the platform for an agent (in the chat with Phay). */
  canRequest: boolean;
};

export type AssistantBonusDto = { id: number; amount: number; note: string | null; givenBy: string | null; createdAt: string | null };

/** A person's role inside one business (see the API's BusinessRole). */
export type BusinessRole = "owner" | "admin" | "moderator";

/** Sections a business role opens: orders (+ dashboard), catalog, settings, insights (analytics + AI). */
export type BusinessPermission = "orders" | "catalog" | "settings" | "insights" | "expenses";

/** Phay's own people: full rights, or onboarding only (no modules, no team). */
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
} & AccountSecurityFlags;

/** Where a login stands (absent from an older API). */
export type AccountSecurityFlags = {
  /** Has set a password (an invited login hasn't yet). */
  activated?: boolean;
  twoFactorEnabled?: boolean;
  emailVerified?: boolean;
  phoneVerified?: boolean;
};

/** A one-time link staff pass on (also emailed when mail works). */
export type AccountLinkDto = { url: string; expiresAt: string; emailed: boolean };

/** A member of Phay's platform team. */
export type AdminPlatformUserDto = {
  id: number;
  name: string;
  email: string;
  role: PlatformRole;
  createdAt: string | null;
} & AccountSecurityFlags;

export type AdminUserDto = {
  id: number;
  name: string;
  email: string;
  isSuperAdmin: boolean;
  /** Null for a business's own people. */
  platformRole: PlatformRole | null;
  /** Role in the current business (null for platform staff that isn't one of its people). */
  businessRole: BusinessRole | null;
  /** What that role opens in the current business. */
  permissions: BusinessPermission[];
  /** The business the admin panel is working on right now (null if the API didn't say). */
  currentTenant: AdminTenantDto | null;
  /** Businesses the user can switch to: their own (platform staff too). */
  tenants: AdminTenantDto[];
  /** The current business's subscription (absent from an older API). */
  subscription?: SubscriptionSummaryDto | null;
  /** Sign-in security (absent from an older API). */
  security?: {
    twoFactorEnabled: boolean;
    twoFactorRequired: boolean;
    /** Must set up 2FA before anything else (see requireAdmin). */
    twoFactorSetupRequired: boolean;
    emailVerified: boolean;
    phoneVerified: boolean;
  };
};

export type SecurityEventDto = {
  id: number;
  event: string;
  label: string;
  warning: boolean;
  ipAddress: string | null;
  userAgent: string | null;
  meta: Record<string, unknown>;
  createdAt: string | null;
  email?: string | null;
  user?: { id: number; name: string } | null;
  actor?: { id?: number; name?: string; email?: string } | null;
};

export type AccountSessionDto = {
  id: number;
  ipAddress: string | null;
  userAgent: string | null;
  createdAt: string | null;
  lastUsedAt: string | null;
  current: boolean;
};

/** GET /admin/security: the signed-in person's own account security. */
export type AccountSecurityDto = {
  twoFactor: { enabled: boolean; required: boolean; confirmedAt: string | null; recoveryCodesLeft: number };
  email: { address: string; verifiedAt: string | null };
  phone: { number: string | null; verifiedAt: string | null; pending: string | null };
  passwordChangedAt: string | null;
  sessions: AccountSessionDto[];
  events: SecurityEventDto[];
  business: { name: string; requireTwoFactor: boolean } | null;
};

export async function getAccountSecurity(): Promise<AccountSecurityDto | null> {
  const result = await adminApiFetch<AccountSecurityDto>("/admin/security");
  return result.data;
}

export type SecurityEventsPageDto = {
  data: SecurityEventDto[];
  meta: { currentPage: number; lastPage: number; total: number };
  events: { value: string; label: string }[];
};

export async function getSecurityEvents(query: string): Promise<SecurityEventsPageDto | null> {
  const result = await adminApiFetchRaw(`/admin/security-events${query}`);
  return result.status === 200 ? (result.json as SecurityEventsPageDto) : null;
}

/* --------------------------------------------------------------- Audience */

export type AudienceGroupDto = { key: string; rows: number; amount: number | null };

/** Who buys and from where, over a period; null: the tables have no such column. */
export type AudienceDto = {
  period: { days: number; from: string; to: string };
  channels: (AudienceGroupDto & { label: string })[] | null;
  gender: AudienceGroupDto[] | null;
  age: AudienceGroupDto[] | null;
  /** Connected social accounts; empty until one can be connected. */
  social: { network: string; handle: string; followers: number; followersChange: number; reach: number; engagement: number }[];
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
  /** On the top plan (or unlimited): the header doesn't invite to premium. */
  premium?: boolean;
};

export type SubscriptionModulePriceDto = {
  /** A module's key, or "agent:<key>" for an AI agent. */
  key: string;
  kind?: "module" | "agent";
  label: string;
  /** What the module gives, one sentence. */
  description: string;
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
  /** On the top plan above the default one (or unlimited). */
  premium?: boolean;
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
  /** AI assistant requests a subscription month; null: no limit. */
  assistantRequests: number | null;
  includedModules: string[];
  isDefault: boolean;
  isActive: boolean;
  sortOrder: number;
  subscriptionsCount?: number;
};

export type ModulePriceDto = { key: string; kind?: "module" | "agent"; label: string; description: string; price: number };

/** The business's own subscription page. */
export type BusinessSubscriptionDto = {
  subscription: SubscriptionDto;
  /** This subscription month's AI assistant requests. */
  assistant: AssistantUsageDto;
  payments: SubscriptionPaymentDto[];
  plans: { id: number; name: string; description: string | null; price: number; assistantRequests: number | null; includedModules: string[] }[];
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
  assistant: AssistantUsageDto & { bonuses: AssistantBonusDto[] };
  plans: { id: number; name: string; price: number; isActive: boolean; assistantRequests: number | null }[];
};

export type AdminSiteDto = {
  id: number;
  name: string;
  domain: string | null;
  publicKey: string;
  allowedOrigins: string[];
  /** Where the platform sends cache refreshes after an edit (the site's /api/revalidate). */
  revalidateUrl: string | null;
  /** The webhook secret is write-only: only whether one is set comes back. */
  hasRevalidateSecret: boolean;
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

/** What a video on the site shows: the result of the work, or about the business. */
export type SiteVideoKind = "before_after" | "info";

export type AdminServiceVideoDto = {
  id: number;
  kind: SiteVideoKind;
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

/**
 * `error` holds the API's real error (status, code, message, request id)
 * when the call failed, and `body` the whole JSON, so a route handler can
 * pass it on to the browser (see bffResponse in ./bff).
 */
export type AdminApiResult<T> = {
  ok: boolean;
  status: number;
  data: T | null;
  error: ApiErrorInfo | null;
  body: unknown;
  requestId: string;
};

export async function adminApiFetch<T>(
  path: string,
  init: RequestInit = {},
): Promise<AdminApiResult<T>> {
  const token = await getAdminToken();
  // Signed out (the sign-in pages render the admin's layout too): the API
  // would only answer 401, so don't ask it.
  if (!token) return signedOut();

  const answer = await requestApi(path, {
    ...init,
    headers: {
      "Content-Type": "application/json",
      Authorization: `Bearer ${token}`,
      ...init.headers,
    },
  });

  const json = answer.json as { data?: T } | null;

  return {
    ok: answer.ok,
    status: answer.status,
    data: json?.data ?? null,
    error: answer.error,
    body: answer.json,
    requestId: answer.requestId,
  };
}

function signedOut<T>(): AdminApiResult<T> {
  const body = { message: "Unauthenticated.", code: "unauthenticated", status: 401 };
  return {
    ok: false,
    status: 401,
    data: null,
    error: { status: 401, code: "unauthenticated", message: body.message, requestId: null },
    body,
    requestId: "",
  };
}

/** Like adminApiFetch, but hands back the whole JSON body (errors included). */
export async function adminApiFetchRaw(
  path: string,
  init: RequestInit = {},
): Promise<{ status: number; json: unknown; requestId: string }> {
  const token = await getAdminToken();

  const answer = await requestApi(path, {
    ...init,
    headers: {
      "Content-Type": "application/json",
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
      ...init.headers,
    },
  });

  return { status: answer.status, json: answer.json, requestId: answer.requestId };
}

export async function getAnalytics(period: AnalyticsPeriod): Promise<AnalyticsDto | null> {
  const result = await adminApiFetch<AnalyticsDto>(`/admin/analytics?period=${period}`);
  return result.data;
}

export async function getTableAnalytics(period: AnalyticsPeriod): Promise<TableAnalyticsDto[]> {
  const result = await adminApiFetch<TableAnalyticsDto[]>(`/admin/analytics/tables?period=${period}`);
  return result.data ?? [];
}

export async function getAudience(period: AnalyticsPeriod): Promise<AudienceDto | null> {
  const result = await adminApiFetch<AudienceDto>(`/admin/analytics/audience?period=${period}`);
  return result.data;
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

/** The signed-in person's chats with the assistant in this business, the latest first. */
/** The person's chats with the assistant, or with one AI agent. */
export async function getAssistantConversations(agent?: string | null): Promise<AssistantConversationDto[]> {
  const query = agent ? `?agent=${encodeURIComponent(agent)}` : "";
  const result = await adminApiFetch<AssistantConversationDto[]>(`/admin/assistant/conversations${query}`);
  return result.data ?? [];
}

/** The business's AI team: every agent, the brand profile and what the person may do. */
export async function getAgents(): Promise<AgentTeamDto | null> {
  const result = await adminApiFetch<AgentTeamDto>("/admin/agents");
  return result.data;
}

/** The person's latest chats, new answers and questions being answered. */
export async function getRecentChats(): Promise<RecentChatsDto | null> {
  const result = await adminApiFetch<RecentChatsDto>("/admin/assistant/recent");
  return result.data;
}

export async function getAssistantConversation(id: number): Promise<AssistantConversationDetailDto | null> {
  const result = await adminApiFetch<AssistantConversationDetailDto>(`/admin/assistant/conversations/${id}`);
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

  const result = await adminApiFetchRaw(`/admin/leads?${query.toString()}`);
  if (result.status !== 200) return empty;

  const json = result.json as {
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

export async function getTableReshape(id: number): Promise<TableReshapeDto | null> {
  const result = await adminApiFetch<TableReshapeDto>(`/admin/table-reshapes/${id}`);
  return result.data;
}

export async function getDataTables(section?: DataTableSection): Promise<AdminDataTableDto[]> {
  const result = await adminApiFetch<AdminDataTableDto[]>(`/admin/data-tables${section ? `?section=${section}` : ""}`);
  return result.data ?? [];
}

/** A row of an order table on a day of the calendar (see the API's CalendarController). */
export type CalendarRowDto = {
  id: number;
  tableId: number;
  /** "orders": a service done; "sales": goods sold. Shown apart. */
  section: "orders" | "sales";
  /** "Y-m-d", the business's local day. */
  date: string;
  /** "H:i" for a date-and-time column or a filled time column; null for the whole day. */
  time: string | null;
  amount: number | null;
  values: Record<string, DataCellValue>;
  /** "google": made from a Google Calendar event; "manual", "import". */
  source: string;
  /** The same order is in that many other tables too: shown and counted once. */
  copies?: number;
  /** May be the same as a row of another table (the owner is asked): the group's key. */
  possibleDuplicate?: string | null;
};

/** One row of a group of rows that are, or may be, one record (see /admin/duplicates). */
export type DuplicateRowDto = {
  id: number;
  tableId: number;
  table: string;
  time: string | null;
  amount: number | null;
  source: string;
  /** Values by column label (the date aside). */
  values: Record<string, DataCellValue>;
  /** Left out of the counts as a copy of that row. */
  copyOf: number | null;
  /** Labels of fields another row of the group has and this one lacks. */
  missing: string[];
};

export type DuplicateGroupDto = {
  key: string;
  section: "orders" | "sales" | "expenses";
  day: string;
  /** exact: every filled field is the same (counted once); near: one lacks a field (both count). */
  kind: "exact" | "near";
  rows: DuplicateRowDto[];
};

export async function getDuplicates(section?: string): Promise<{ groups: DuplicateGroupDto[]; copies: number } | null> {
  const result = await adminApiFetch<{ groups: DuplicateGroupDto[]; copies: number }>(
    `/admin/duplicates${section ? `?section=${section}` : ""}`,
  );
  return result.data;
}

/** An event of a synced Google calendar, not turned into a table row yet. */
export type CalendarGoogleEventDto = {
  id: number;
  eventId: string;
  date: string;
  /** null: an all-day event. */
  time: string | null;
  end: string | null;
  title: string;
  location: string | null;
  description: string | null;
  /** The Google calendar's name and colour, and whose account it is. */
  calendar: string;
  color: string | null;
  account: string | null;
  /** The event in Google Calendar. */
  link: string | null;
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
  google: CalendarGoogleEventDto[];
  /** Employees away on some of these days. */
  absences?: AbsenceDto[];
  /** Interviews with candidates (only for those who manage the team). */
  interviews?: CalendarInterviewDto[];
  /** Groups of rows of this range that are, or may be, one record. */
  duplicates?: number;
};

export type CalendarInterviewDto = {
  id: number;
  date: string;
  time: string;
  name: string;
  opening: string | null;
  stage: CandidateStage;
};

/** What «turn into a table» would do now. */
export type GoogleTablePreviewDto = {
  /** The current table made from the calendars, if any. */
  table: { id: number; name: string; createdAt: string | null } | null;
  /** The table was split into these (a month's each): new events go to their month's, changes update rows in place. */
  spread?: { id: number; name: string }[];
  events: number;
  new: number;
  changed: number;
  removed: number;
  /** Rows edited by hand whose event changed or was deleted: kept as they are. */
  keptEdited: number;
  upToDate: boolean;
};

export type GoogleCalendarDto = {
  id: number;
  name: string;
  color: string | null;
  primary: boolean;
  selected: boolean;
  syncedAt: string | null;
  events: number;
};

/** The person's Google Calendar connection in this business. */
export type GoogleStateDto = {
  /** The platform has its Google keys (GOOGLE_CLIENT_ID…). */
  configured: boolean;
  connection: {
    email: string | null;
    connectedAt: string | null;
    syncedAt: string | null;
    /** Google refused the access: connect again. */
    needsReconnect: boolean;
    calendars: GoogleCalendarDto[];
  } | null;
  pending: GoogleTablePreviewDto;
};

export async function getGoogleState(): Promise<GoogleStateDto | null> {
  const result = await adminApiFetch<GoogleStateDto>("/admin/google");
  return result.data;
}

export type SocialNetwork = "instagram" | "facebook";

/** Post figures the same way for the business and its competitors (App\Social\PostStats). */
export type SocialPostStatsDto = {
  posts: number;
  from?: string;
  to?: string;
  lastPostAt?: string;
  postsLast30Days?: number;
  postsLast90Days?: number;
  postsPerWeek?: number;
  avgLikes?: number;
  avgComments?: number;
  avgShares?: number;
  avgSaves?: number;
  avgReach?: number;
  avgViews?: number;
  hiddenLikes?: boolean;
  engagementRate?: number;
  byKind?: { kind: string; posts: number; avgLikes?: number; avgComments?: number; avgReach?: number; avgViews?: number }[];
  bestWeekdays?: { group: string; posts: number; avgEngagement: number }[];
  bestHours?: { group: string; posts: number; avgEngagement: number }[];
};

export type SocialPostDto = {
  postedAt?: string;
  kind?: string;
  caption?: string;
  likes?: number;
  comments?: number;
  shares?: number;
  saves?: number;
  reach?: number;
  views?: number;
  permalink?: string;
};

export type SocialAccountInsightsDto = {
  id: number;
  network: SocialNetwork;
  handle: string;
  username?: string;
  name?: string;
  picture?: string;
  url?: string;
  profile?: { biography?: string; website?: string; category?: string; link?: string };
  followers?: number;
  followersChange7Days?: number;
  followersChange30Days?: number;
  follows?: number;
  mediaCount?: number;
  last28Days?: {
    from: string | null;
    to: string | null;
    totals: Record<string, number>;
    previous28Days: Record<string, number>;
    changePercent: Record<string, number>;
  };
  /** Followers by age, gender, city, country (from 100 followers). */
  followersAudience?: Partial<Record<"age" | "gender" | "city" | "country", Record<string, number>>>;
  postsLast90Days: SocialPostStatsDto;
  topPosts?: SocialPostDto[];
  syncedAt?: string;
};

export type SocialCompetitorDto = {
  id: number;
  username: string;
  status: "pending" | "ok" | "not_found" | "error";
  error?: string;
  name?: string;
  picture?: string;
  biography?: string;
  website?: string;
  url: string;
  adLibraryUrl: string;
  followers?: number;
  followersChange30Days?: number;
  follows?: number;
  mediaCount?: number;
  stats?: SocialPostStatsDto;
  recentPosts?: SocialPostDto[];
  syncedAt?: string;
};

export type SocialComparisonRowDto = {
  own: boolean;
  username: string | null;
  followers: number | null;
  mediaCount: number | null;
  postsPerWeek: number | null;
  avgLikes: number | null;
  avgComments: number | null;
  engagementRate: number | null;
  hiddenLikes: boolean;
  lastPostAt: string | null;
};

export type SocialStateDto = {
  /** The platform has its Meta app keys (META_APP_ID…). */
  configured: boolean;
  connection: {
    name: string | null;
    connectedAt: string | null;
    syncedAt: string | null;
    expiresAt: string | null;
    expiringSoon: boolean;
    /** Meta refused the access: connect again. */
    needsReconnect: boolean;
    missingPermissions: string[];
    syncError: string | null;
    accounts: {
      id: number;
      network: SocialNetwork;
      pageId: string | null;
      handle: string;
      name: string | null;
      picture: string | null;
      followers: number | null;
      selected: boolean;
    }[];
  } | null;
  insights: SocialAccountInsightsDto[];
  competitors: SocialCompetitorDto[];
  comparison: SocialComparisonRowDto[];
  maxCompetitors: number;
};

export async function getSocialState(): Promise<SocialStateDto | null> {
  const result = await adminApiFetch<SocialStateDto>("/admin/social");
  return result.data;
}

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
  email?: string | null;
  /** First working day, birthday (YYYY-MM-DD). */
  hiredOn?: string | null;
  birthDate?: string | null;
  /** What a new employee still has to go through. */
  onboarding?: OnboardingItemDto[] | null;
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
  /** The usual first steps of a new employee, to start a checklist from. */
  onboardingDefault?: string[];
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

/** What was spent in a day, week, month, quarter or year (see the API's ExpenseStatistics). */
export type ExpenseTotalsDto = {
  total: number;
  inProfit: number;
  excluded: number;
  recurring: number;
  oneOff: number;
  count: number;
};

export type ExpenseLineDto = {
  date: string;
  amount: number;
  category: string;
  inProfit: boolean;
  recurring: boolean;
  item: string | null;
  supplier: string | null;
  payment: string | null;
  performer: string | null;
  project: string | null;
  note: string | null;
  tableId: number | null;
  table: string | null;
  rowId: number | null;
};

export type ExpenseStatisticsDto = {
  by: PeriodKind;
  today: string;
  period: PeriodDto;
  previous: PeriodDto;
  unfinished: boolean;
  totals: ExpenseTotalsDto & { previous: number; perDay: number | null; tax: number | null };
  series: (PeriodDto & ExpenseTotalsDto)[];
  inside: { key: string; start: string; label: string; total: number; count: number }[];
  categories: FinanceCategoryDto[];
  /** Last period's spending by category name. */
  previousCategories: Record<string, number>;
  breakdowns: { role: string; label: string; distinct: number; values: { value: string; amount: number; count: number }[] }[];
  largest: ExpenseLineDto[];
  rows: ExpenseLineDto[];
  count: number;
  has: { tables: boolean; recurring: boolean; roles: string[] };
};

export async function getExpenseStatistics(params: { by?: PeriodKind; date?: string }): Promise<ExpenseStatisticsDto | null> {
  const query = new URLSearchParams();
  if (params.by) query.set("by", params.by);
  if (params.date) query.set("date", params.date);
  const result = await adminApiFetch<ExpenseStatisticsDto>(`/admin/expenses/statistics?${query}`);
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

/**
 * One business from the platform level (platform staff): how long and how
 * actively it uses Phay, its subscription, modules, agents and AI usage.
 * Never its own data (orders, sales, money, conversations).
 */
export type BusinessOverviewDto = {
  tenant: {
    id: number;
    name: string;
    logo: string | null;
    slug: string;
    status: "active" | "suspended";
    defaultLocale: string;
    timezone: string | null;
    createdAt: string | null;
    address: Partial<Record<"hy" | "ru" | "en", string>>;
    contacts: { type: ContactChannelType; value: string; label: string | null }[];
  };
  activity: {
    createdAt: string | null;
    daysOnPlatform: number | null;
    /** Days its own people opened the admin, since the start. */
    activeDays: number;
    /** The same over the last 30 days. */
    activeDaysRecent: number;
    firstActiveDay: string | null;
    lastActiveDay: string | null;
    lastSeenAt: string | null;
    /** The last 30 days, oldest first. */
    recent: { date: string; active: boolean }[];
  };
  subscription: SubscriptionDto;
  /** The platform's money: the super admin only (null for a moderator). */
  payments: { count: number; total: number; latest: SubscriptionPaymentDto[] } | null;
  modules: { key: string; label: string; active: boolean }[];
  agents: { key: string; persona: Record<string, string>; label: string; active: boolean }[];
  assistant: AssistantUsageDto & { bonuses: AssistantBonusDto[] | null };
  people: { logins: number; activated: number; byRole: Record<BusinessRole, number>; employees: number };
  support: { unread: number; lastMessageAt: string | null };
  sites: number;
};

export async function getBusinessOverviewAdmin(tenantId: number): Promise<BusinessOverviewDto | null> {
  const result = await adminApiFetch<BusinessOverviewDto>(`/admin/tenants/${tenantId}/overview`);
  return result.data;
}

export async function getTenantSubscriptionAdmin(tenantId: number): Promise<TenantSubscriptionDetailDto | null> {
  const result = await adminApiFetch<TenantSubscriptionDetailDto>(`/admin/tenants/${tenantId}/subscription`);
  return result.data;
}

/** A blog post of the business's site (the site module). */
export type PostCategory = "cleaning-tips" | "upholstery-care" | "auto-care" | "guides" | "company-news";
export type PostStatus = "draft" | "published";

export type AdminPostDto = {
  id: number;
  slug: string;
  category: PostCategory;
  status: PostStatus;
  publishedAt: string | null;
  authorName: string;
  featuredImagePath: string | null;
  title: AdminTranslated;
  excerpt: AdminTranslated;
  updatedAt: string | null;
  /** Only when one post is read. */
  content?: AdminTranslated;
  seoTitle?: AdminTranslated;
  seoDescription?: AdminTranslated;
};

export async function getPostsAdmin(): Promise<AdminPostDto[] | null> {
  const result = await adminApiFetch<AdminPostDto[]>("/admin/posts");
  return result.ok ? (result.data ?? []) : null;
}

export async function getPostAdmin(id: number): Promise<AdminPostDto | null> {
  const result = await adminApiFetch<AdminPostDto>(`/admin/posts/${id}`);
  return result.data;
}

/** A message from the feedback form of the business's site. */
export type FeedbackMessageDto = {
  id: number;
  name: string;
  phone: string | null;
  email: string | null;
  message: string;
  locale: string | null;
  readAt: string | null;
  createdAt: string | null;
};

export type FeedbackPageDto = {
  items: FeedbackMessageDto[];
  meta: { total: number; currentPage: number; lastPage: number; unread: number };
};

export async function getFeedbackMessages(page = 1): Promise<FeedbackPageDto | null> {
  const { status, json } = await adminApiFetchRaw(`/admin/feedback?page=${page}`);
  if (status !== 200 || !json) return null;
  const body = json as { data: FeedbackMessageDto[]; meta: FeedbackPageDto["meta"] };
  return { items: body.data, meta: body.meta };
}

// ---- The team: time off, hiring, onboarding (the HR agent prepares the same as cards) ----

export type AbsenceKind = "vacation" | "day_off" | "sick" | "unpaid" | "other";

export type AbsenceDto = {
  id: number;
  employee: { id: number; name: string } | null;
  kind: AbsenceKind;
  kindLabel: string;
  /** Inclusive, YYYY-MM-DD. */
  from: string;
  to: string;
  days: number;
  note: string | null;
};

export type TeamAbsencesDto = {
  today: string;
  absences: AbsenceDto[];
  /** Vacation days this year by employee id. */
  vacationDays: Record<string, number>;
};

export type OnboardingItemDto = { title: string; done: boolean };

export type CandidateStage = "new" | "screening" | "interview" | "offer" | "hired" | "rejected";

export type JobOpeningDto = {
  id: number;
  title: string;
  description: string | null;
  salary: string | null;
  employment: "full_time" | "part_time" | "shift" | "freelance" | null;
  status: "open" | "paused" | "closed";
  createdAt: string | null;
};

export type CandidateDto = {
  id: number;
  openingId?: number;
  name: string;
  phone?: string;
  email?: string;
  source?: string;
  stage: CandidateStage;
  stageLabel: string;
  /** "YYYY-MM-DD HH:MM", the business's local time. */
  interviewAt?: string;
  rating?: number;
  notes?: string;
  employeeId?: number;
  updatedAt?: string;
};

export type TeamHiringDto = {
  openings: JobOpeningDto[];
  candidates: CandidateDto[];
  stages: { value: CandidateStage; label: string }[];
};

/** A change of the team the HR agent prepared (the API's HrChanges), shown as a card in the chat. */
export type HrChangeDto = {
  id: number;
  status: "planned" | "applied" | "undone" | "discarded";
  title: string;
  lines: string[];
  /** It removes something (an absence...). */
  deletes: boolean;
  stale: string | null;
  undoBlocked: string | null;
  /** Where the result is seen in the admin. */
  link: string;
  appliedAt: string | null;
  undoneAt: string | null;
};

export async function getTeamAbsences(): Promise<TeamAbsencesDto | null> {
  const result = await adminApiFetch<TeamAbsencesDto>("/admin/team/absences");
  return result.data;
}

export async function getTeamHiring(): Promise<TeamHiringDto | null> {
  const result = await adminApiFetch<TeamHiringDto>("/admin/team/hiring");
  return result.data;
}
