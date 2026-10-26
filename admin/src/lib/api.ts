// Server-only. Public-API DTOs the admin reuses (enums, ServiceDto for the
// service pickers). Business sites keep their own copy in their repositories:
// keep them in sync until they share a generated client.

export type Translated = string;

export type ServiceCategoryDto = {
  id: number;
  slug: string;
  icon: string | null;
  name: Translated;
  description: Translated | null;
  services?: ServiceDto[];
};

export type ServiceStatus = "draft" | "coming_soon" | "active" | "temporarily_unavailable" | "archived";
export type CarBodyType = "compact" | "sedan" | "wagon" | "crossover" | "suv" | "minivan" | "minibus" | "commercial";
export type InteriorMaterial = "fabric" | "leather" | "alcantara" | "mixed" | "plastic";
export type ContaminationType =
  | "dust"
  | "food_drinks"
  | "coffee"
  | "grease_oil"
  | "blood"
  | "urine"
  | "vomit"
  | "ink"
  | "pet_hair"
  | "tobacco_odor"
  | "dampness"
  | "unknown";
export type SoilingLevel = "light" | "moderate" | "heavy";
export type ServicePriceType = "fixed" | "from" | "on_inspection" | "range" | "by_car_type";

export type ServiceGalleryItemDto = {
  id: number;
  serviceSlug: string | null;
  carType: CarBodyType | null;
  label: Translated | null;
  description: Translated | null;
  beforeImage: string;
  afterImage: string;
  altBefore: Translated | null;
  altAfter: Translated | null;
  performedAt: string | null;
};

export type ServiceVideoDto = {
  id: number;
  title: Translated | null;
  videoPath: string;
  posterImage: string | null;
};

export type ServicePackageDto = {
  id: number;
  slug: string;
  status: ServiceStatus;
  name: Translated;
  shortDescription: Translated | null;
  description: Translated | null;
  includedItems: string[] | null;
  priceType: ServicePriceType | null;
  priceAmount: number | null;
  priceMin: number | null;
  priceMax: number | null;
  priceByCarType: Record<string, number>;
  durationMinutes: number | null;
  image: string | null;
};

export type ServiceDto = {
  id: number;
  slug: string;
  icon: string | null;
  image: string | null;
  status: ServiceStatus;
  isOrderable: boolean;
  name: Translated;
  shortDescription: Translated | null;
  description: Translated | null;
  seoTitle: Translated | null;
  seoDescription: Translated | null;
  ogTitle: Translated | null;
  ogDescription: Translated | null;
  ogImage: string | null;
  canonicalOverride: string | null;
  seoRobots: "index" | "noindex" | null;
  whoItsFor: Translated | null;
  includedItems: string[] | null;
  excludedItems: string[] | null;
  processSteps: string[] | null;
  equipment: Translated | null;
  durationNote: Translated | null;
  priceFactors: string[] | null;
  faq: { question: string; answer: string }[] | null;
  warnings: string[] | null;
  dryingTimeNote: Translated | null;
  carTypes: CarBodyType[];
  materials: InteriorMaterial[];
  contaminationTypes: ContaminationType[];
  priceType: ServicePriceType | null;
  priceAmount: number | null;
  priceMin: number | null;
  priceMax: number | null;
  priceByCarType: Record<string, number>;
  durationMinutes: number | null;
  updatedAt: string | null;
  category?: ServiceCategoryDto;
  products?: ProductSummaryDto[];
  galleryItems?: ServiceGalleryItemDto[];
};

export type AdditionalServiceCategory = "upholstery" | "textile" | "windows";
export type PriceUnit = "flat" | "sqm";

export type AdditionalServiceDto = {
  id: number;
  slug: string;
  category: AdditionalServiceCategory;
  name: Translated;
  note: Translated | null;
  alt: Translated;
  image: string;
  priceAmount: number;
  priceUnit: PriceUnit;
  discountPercent: number;
  serviceSlug: string | null;
};

export type LocalizedSlugs = { hy: string; ru: string; en: string };

export type ProductCategoryDto = {
  id: number;
  slug: string;
  slugs: LocalizedSlugs;
  icon: string | null;
  name: Translated;
  shortDescription: Translated | null;
  sortOrder: number;
};

export type ProductVolume = { value: number; unit: string };
export type ProductAvailability = "in_stock" | "out_of_stock" | "pre_order";
export type ProductCompositionType = "acidic" | "neutral" | "alkaline" | "enzymatic" | "oxidizing" | "solvent";

export type ProductSummaryDto = {
  id: number;
  slug: string;
  categorySlug: string | null;
  brand: string | null;
  name: Translated;
  shortDescription: Translated | null;
  image: string | null;
  alt: Translated | null;
  volume: ProductVolume | null;
  priceAmount: number | null;
  oldPriceAmount: number | null;
  availabilityStatus: ProductAvailability | null;
};

export type ProductImageDto = { path: string; alt: Translated | null; isPrimary: boolean };
export type DilutionRow = { ratio: string; useCase: string };

export type ProductDetailDto = {
  id: number;
  slug: string;
  slugs: LocalizedSlugs;
  sku: string | null;
  gtin: string | null;
  brand: string | null;
  manufacturer: string | null;
  countryOfOrigin: string | null;
  volume: ProductVolume | null;
  name: Translated;
  shortDescription: Translated | null;
  description: Translated;
  alt: Translated | null;
  image: string | null;
  images: ProductImageDto[];
  priceAmount: number | null;
  oldPriceAmount: number | null;
  stockQuantity: number | null;
  availabilityStatus: ProductAvailability | null;
  category: ProductCategoryDto | null;
  categories: ProductCategoryDto[];
  stains: string[] | null;
  surfaces: string[] | null;
  forbiddenSurfaces: string[] | null;
  phValue: number | null;
  compositionType: ProductCompositionType | null;
  dilutionTable: DilutionRow[] | null;
  usageSteps: string[] | null;
  requiresExtractor: boolean | null;
  consumptionNote: Translated | null;
  contactTime: Translated | null;
  rinseMethod: Translated | null;
  safetyNotes: string[] | null;
  storageConditions: Translated | null;
  shelfLife: Translated | null;
  sdsUrl: string | null;
  certificateNote: Translated | null;
  seoTitle: Translated;
  seoDescription: Translated;
  ogTitle: Translated | null;
  ogDescription: Translated | null;
  ogImage: string | null;
  canonicalOverride: string | null;
  seoRobots: "index" | "noindex" | null;
  services: ServiceDto[];
  relatedProducts: ProductSummaryDto[];
  accessories: ProductSummaryDto[];
  taskPages: { slug: string; title: string }[];
  updatedAt: string | null;
};

export type TaskPageDto = {
  id: number;
  slug: string;
  slugs: LocalizedSlugs;
  title: Translated;
  intro: Translated | null;
  selectionRules: string[] | null;
  precautions: string[] | null;
  seoTitle: Translated;
  seoDescription: Translated | null;
  relatedService: ServiceDto | null;
  products: ProductSummaryDto[];
  updatedAt: string | null;
};

export type CleaningRateMatrix = Record<"apartment" | "house" | "office", Record<"regular" | "general" | "postConstruction", number>>;

export type RoomRateMatrix = Record<
  "studio" | "oneRoom" | "twoRoom" | "threeRoom" | "fourPlusRoom",
  Record<"regular" | "general" | "postConstruction", number>
>;

export type DryCleaningRates = {
  sofa2: number;
  sofa3: number;
  sofaCorner: number;
  armchair: number;
  chair: number;
  mattressSingle: number;
  mattressDouble: number;
  carpet: number;
  steam: number;
};

export type CleaningDiscounts = Record<"regular" | "general" | "postConstruction", number>;

export type CalculatorSettingsDto = {
  cleaningRates: CleaningRateMatrix;
  roomRates: RoomRateMatrix;
  cleaningDiscounts: CleaningDiscounts;
  bathroomRate: number;
  vitrageRate: number;
  extraFloorRate: number;
  heavySoilingMultiplier: number;
  cleaningMinimum: number;
  dryCleaningRates: DryCleaningRates;
};

export type PostSummaryDto = {
  id: number;
  slug: string;
  category: string;
  tags: string[];
  authorName: string;
  publishedAt: string | null;
  updatedAt: string | null;
  featuredImage: { path: string; alt: string | null } | null;
  title: Translated;
  excerpt: Translated | null;
};

export type PostDto = PostSummaryDto & {
  content: Translated;
  seoTitle: Translated | null;
  seoDescription: Translated | null;
  faq: { question: string; answer: string }[] | null;
  relatedServices: ServiceDto[];
  relatedPosts: PostSummaryDto[];
};

export type PaginationMeta = {
  currentPage: number;
  lastPage: number;
  total: number;
};

export type ContactChannelType =
  | "phone"
  | "whatsapp"
  | "telegram"
  | "instagram"
  | "facebook"
  | "tiktok"
  | "viber"
  | "email"
  | "other";

export type ContactChannelDto = {
  type: ContactChannelType;
  value: string;
  label: string | null;
  isPrimary: boolean;
};

/** The modules the platform turns on per business; orders and sales every business has. */
export type FeatureFlags = {
  services: boolean;
  market: boolean;
  site: boolean;
  social: boolean;
};

export type SiteSettingsDto = {
  address: Translated | null;
  channels: ContactChannelDto[];
  features: FeatureFlags;
};

const API_BASE =
  process.env.NEXT_INTERNAL_API_URL ??
  process.env.NEXT_PUBLIC_API_URL ??
  "http://localhost:8080/api/v1";

// Active services from the public catalog, used to fill the service pickers
// on the order/product/task-page forms. Not cached: the admin should always
// see the current list.
export async function getServices(locale: string): Promise<ServiceDto[]> {
  try {
    const response = await fetch(`${API_BASE}/services?lang=${locale}`, {
      cache: "no-store",
      signal: AbortSignal.timeout(5000),
    });

    if (!response.ok) {
      throw new Error(`API request to /services failed with ${response.status}`);
    }

    const json = (await response.json()) as { data: ServiceDto[] };
    return json.data;
  } catch (error) {
    console.warn(`[api] getServices(${locale}) failed:`, error);
    return [];
  }
}
