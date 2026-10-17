"use client";

import { useMemo, useState } from "react";
import { useRouter } from "next/navigation";
import Link from "next/link";
import type { AdminProductDto, AdminProductStatus, AdminTranslated } from "@/lib/adminApi";

type Locale = "hy" | "ru" | "en";
const LOCALES: { key: Locale; label: string }[] = [
  { key: "hy", label: "Հայերեն" },
  { key: "ru", label: "Ռուսերեն" },
  { key: "en", label: "Անգլերեն" },
];

const STATUSES: AdminProductStatus[] = ["draft", "published", "archived"];
const COMPOSITION_TYPES = ["", "acidic", "neutral", "alkaline", "enzymatic", "oxidizing", "solvent"];
const AVAILABILITY_STATUSES = ["", "in_stock", "out_of_stock", "pre_order"];
const SEO_ROBOTS = ["index", "noindex"] as const;

const fieldClasses =
  "w-full rounded-md border border-border bg-surface px-2.5 py-1.5 text-sm text-ink placeholder:text-ink-faint transition-colors focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary-light disabled:cursor-not-allowed disabled:bg-surface-secondary disabled:text-ink-faint";
const labelClasses = "text-xs font-medium text-ink-muted";
const sectionClasses = "space-y-4 rounded-lg border border-border bg-surface p-5";
const pillLabelClasses = (active: boolean) =>
  `flex cursor-pointer items-center gap-1.5 rounded-full border px-3 py-1.5 text-xs ${
    active ? "border-primary bg-primary-light text-primary" : "border-border text-ink-muted"
  }`;

function toTextareaValue(list: unknown): string {
  if (!Array.isArray(list)) return "";
  return list.map((item) => (typeof item === "string" ? item : JSON.stringify(item))).join("\n");
}

function fromTextareaValue(value: string): string[] {
  return value
    .split("\n")
    .map((line) => line.trim())
    .filter((line) => line.length > 0);
}

function toDilutionTextareaValue(list: unknown): string {
  if (!Array.isArray(list)) return "";
  return list
    .map((row) => {
      if (row && typeof row === "object" && "ratio" in row) {
        const r = row as { ratio?: string; useCase?: string; use_case?: string };
        return `${r.ratio ?? ""} — ${r.useCase ?? r.use_case ?? ""}`;
      }
      return "";
    })
    .filter(Boolean)
    .join("\n");
}

function fromDilutionTextareaValue(value: string): { ratio: string; use_case: string }[] {
  return value
    .split("\n")
    .map((line) => line.trim())
    .filter(Boolean)
    .map((line) => {
      const [ratio, ...rest] = line.split("—");
      return { ratio: (ratio ?? "").trim(), use_case: rest.join("—").trim() };
    });
}

type TranslatedListState = Record<Locale, string>;

function translatedListFromDto(dto: Record<string, unknown>): TranslatedListState {
  return {
    hy: toTextareaValue(dto?.hy),
    ru: toTextareaValue(dto?.ru),
    en: toTextareaValue(dto?.en),
  };
}

type FormState = {
  slugHy: string;
  slugRu: string;
  slugEn: string;
  status: AdminProductStatus;
  name: AdminTranslated;
  shortDescription: AdminTranslated;
  description: AdminTranslated;
  alt: AdminTranslated;
  image: string;
  brand: string;
  manufacturer: string;
  countryOfOrigin: string;
  sku: string;
  gtin: string;
  volumeValue: string;
  volumeUnit: string;
  priceAmount: string;
  oldPriceAmount: string;
  stockQuantity: string;
  availabilityStatus: string;
  sortOrder: string;
  adminNote: string;
  stains: TranslatedListState;
  surfaces: TranslatedListState;
  forbiddenSurfaces: TranslatedListState;
  phValue: string;
  compositionType: string;
  dilutionTable: TranslatedListState;
  usageSteps: TranslatedListState;
  requiresExtractor: boolean;
  consumptionNote: AdminTranslated;
  contactTime: AdminTranslated;
  rinseMethod: AdminTranslated;
  safetyNotes: TranslatedListState;
  storageConditions: AdminTranslated;
  shelfLife: AdminTranslated;
  batchTracked: boolean;
  sdsUrl: string;
  certificateNote: AdminTranslated;
  seoTitle: AdminTranslated;
  seoDescription: AdminTranslated;
  ogTitle: AdminTranslated;
  ogDescription: AdminTranslated;
  ogImage: string;
  canonicalOverride: string;
  seoRobots: "index" | "noindex";
  primaryKeyword: string;
  secondaryKeywords: string;
  categoryIds: number[];
  primaryCategoryId: number | null;
  serviceIds: number[];
  relatedProductIds: number[];
  accessoryProductIds: number[];
};

function toFormState(item?: AdminProductDto): FormState {
  return {
    slugHy: item?.slugHy ?? "",
    slugRu: item?.slugRu ?? "",
    slugEn: item?.slugEn ?? "",
    status: item?.status ?? "draft",
    name: { hy: item?.name.hy ?? "", ru: item?.name.ru ?? "", en: item?.name.en ?? "" },
    shortDescription: { hy: item?.shortDescription.hy ?? "", ru: item?.shortDescription.ru ?? "", en: item?.shortDescription.en ?? "" },
    description: { hy: item?.description.hy ?? "", ru: item?.description.ru ?? "", en: item?.description.en ?? "" },
    alt: { hy: item?.alt.hy ?? "", ru: item?.alt.ru ?? "", en: item?.alt.en ?? "" },
    image: item?.image ?? "",
    brand: item?.brand ?? "",
    manufacturer: item?.manufacturer ?? "",
    countryOfOrigin: item?.countryOfOrigin ?? "",
    sku: item?.sku ?? "",
    gtin: item?.gtin ?? "",
    volumeValue: item?.volumeValue?.toString() ?? "",
    volumeUnit: item?.volumeUnit ?? "",
    priceAmount: item?.priceAmount?.toString() ?? "",
    oldPriceAmount: item?.oldPriceAmount?.toString() ?? "",
    stockQuantity: item?.stockQuantity?.toString() ?? "",
    availabilityStatus: item?.availabilityStatus ?? "",
    sortOrder: item?.sortOrder?.toString() ?? "0",
    adminNote: item?.adminNote ?? "",
    stains: translatedListFromDto(item?.stains ?? {}),
    surfaces: translatedListFromDto(item?.surfaces ?? {}),
    forbiddenSurfaces: translatedListFromDto(item?.forbiddenSurfaces ?? {}),
    phValue: item?.phValue?.toString() ?? "",
    compositionType: item?.compositionType ?? "",
    dilutionTable: {
      hy: toDilutionTextareaValue((item?.dilutionTable as Record<string, unknown>)?.hy),
      ru: toDilutionTextareaValue((item?.dilutionTable as Record<string, unknown>)?.ru),
      en: toDilutionTextareaValue((item?.dilutionTable as Record<string, unknown>)?.en),
    },
    usageSteps: translatedListFromDto((item?.usageSteps as Record<string, unknown>) ?? {}),
    requiresExtractor: item?.requiresExtractor ?? false,
    consumptionNote: { hy: item?.consumptionNote.hy ?? "", ru: item?.consumptionNote.ru ?? "", en: item?.consumptionNote.en ?? "" },
    contactTime: { hy: item?.contactTime.hy ?? "", ru: item?.contactTime.ru ?? "", en: item?.contactTime.en ?? "" },
    rinseMethod: { hy: item?.rinseMethod.hy ?? "", ru: item?.rinseMethod.ru ?? "", en: item?.rinseMethod.en ?? "" },
    safetyNotes: translatedListFromDto((item?.safetyNotes as Record<string, unknown>) ?? {}),
    storageConditions: { hy: item?.storageConditions.hy ?? "", ru: item?.storageConditions.ru ?? "", en: item?.storageConditions.en ?? "" },
    shelfLife: { hy: item?.shelfLife.hy ?? "", ru: item?.shelfLife.ru ?? "", en: item?.shelfLife.en ?? "" },
    batchTracked: item?.batchTracked ?? false,
    sdsUrl: item?.sdsUrl ?? "",
    certificateNote: { hy: item?.certificateNote.hy ?? "", ru: item?.certificateNote.ru ?? "", en: item?.certificateNote.en ?? "" },
    seoTitle: { hy: item?.seoTitle.hy ?? "", ru: item?.seoTitle.ru ?? "", en: item?.seoTitle.en ?? "" },
    seoDescription: { hy: item?.seoDescription.hy ?? "", ru: item?.seoDescription.ru ?? "", en: item?.seoDescription.en ?? "" },
    ogTitle: { hy: item?.ogTitle.hy ?? "", ru: item?.ogTitle.ru ?? "", en: item?.ogTitle.en ?? "" },
    ogDescription: { hy: item?.ogDescription.hy ?? "", ru: item?.ogDescription.ru ?? "", en: item?.ogDescription.en ?? "" },
    ogImage: item?.ogImage ?? "",
    canonicalOverride: item?.canonicalOverride ?? "",
    seoRobots: (item?.seoRobots as "index" | "noindex") ?? "index",
    primaryKeyword: item?.primaryKeyword ?? "",
    secondaryKeywords: (item?.secondaryKeywords ?? []).join("\n"),
    categoryIds: item?.categoryIds ?? [],
    primaryCategoryId: item?.primaryCategoryId ?? null,
    serviceIds: item?.serviceIds ?? [],
    relatedProductIds: item?.relatedProductIds ?? [],
    accessoryProductIds: item?.accessoryProductIds ?? [],
  };
}

function buildPayload(form: FormState) {
  return {
    slug_hy: form.slugHy.trim(),
    slug_ru: form.slugRu.trim(),
    slug_en: form.slugEn.trim(),
    status: form.status,
    name: form.name,
    short_description: form.shortDescription,
    description: form.description,
    alt: form.alt,
    image: form.image.trim() || null,
    brand: form.brand.trim() || null,
    manufacturer: form.manufacturer.trim() || null,
    country_of_origin: form.countryOfOrigin.trim() || null,
    sku: form.sku.trim() || null,
    gtin: form.gtin.trim() || null,
    volume_value: form.volumeValue ? Number(form.volumeValue) : null,
    volume_unit: form.volumeUnit.trim() || null,
    price_amount: form.priceAmount ? Number(form.priceAmount) : null,
    old_price_amount: form.oldPriceAmount ? Number(form.oldPriceAmount) : null,
    stock_quantity: form.stockQuantity ? Number(form.stockQuantity) : null,
    availability_status: form.availabilityStatus || null,
    sort_order: Number(form.sortOrder) || 0,
    admin_note: form.adminNote.trim() || null,
    stains: {
      hy: fromTextareaValue(form.stains.hy),
      ru: fromTextareaValue(form.stains.ru),
      en: fromTextareaValue(form.stains.en),
    },
    surfaces: {
      hy: fromTextareaValue(form.surfaces.hy),
      ru: fromTextareaValue(form.surfaces.ru),
      en: fromTextareaValue(form.surfaces.en),
    },
    forbidden_surfaces: {
      hy: fromTextareaValue(form.forbiddenSurfaces.hy),
      ru: fromTextareaValue(form.forbiddenSurfaces.ru),
      en: fromTextareaValue(form.forbiddenSurfaces.en),
    },
    ph_value: form.phValue ? Number(form.phValue) : null,
    composition_type: form.compositionType || null,
    dilution_table: {
      hy: fromDilutionTextareaValue(form.dilutionTable.hy),
      ru: fromDilutionTextareaValue(form.dilutionTable.ru),
      en: fromDilutionTextareaValue(form.dilutionTable.en),
    },
    usage_steps: {
      hy: fromTextareaValue(form.usageSteps.hy),
      ru: fromTextareaValue(form.usageSteps.ru),
      en: fromTextareaValue(form.usageSteps.en),
    },
    requires_extractor: form.requiresExtractor,
    consumption_note: form.consumptionNote,
    contact_time: form.contactTime,
    rinse_method: form.rinseMethod,
    safety_notes: {
      hy: fromTextareaValue(form.safetyNotes.hy),
      ru: fromTextareaValue(form.safetyNotes.ru),
      en: fromTextareaValue(form.safetyNotes.en),
    },
    storage_conditions: form.storageConditions,
    shelf_life: form.shelfLife,
    batch_tracked: form.batchTracked,
    sds_url: form.sdsUrl.trim() || null,
    certificate_note: form.certificateNote,
    seo_title: form.seoTitle,
    seo_description: form.seoDescription,
    og_title: form.ogTitle,
    og_description: form.ogDescription,
    og_image: form.ogImage.trim() || null,
    canonical_override: form.canonicalOverride.trim() || null,
    seo_robots: form.seoRobots,
    primary_keyword: form.primaryKeyword.trim() || null,
    secondary_keywords: fromTextareaValue(form.secondaryKeywords),
    category_ids: form.categoryIds,
    primary_category_id: form.primaryCategoryId,
    service_ids: form.serviceIds,
    related_product_ids: form.relatedProductIds,
    accessory_product_ids: form.accessoryProductIds,
  };
}

function TranslatedInput({
  label,
  value,
  onChange,
  multiline = false,
  rows = 3,
}: {
  label: string;
  value: AdminTranslated;
  onChange: (next: AdminTranslated) => void;
  multiline?: boolean;
  rows?: number;
}) {
  return (
    <div className="space-y-1.5">
      <p className={labelClasses}>{label}</p>
      <div className="grid gap-2 sm:grid-cols-3">
        {LOCALES.map(({ key, label: localeLabel }) =>
          multiline ? (
            <textarea
              key={key}
              value={value[key] ?? ""}
              placeholder={localeLabel}
              rows={rows}
              onChange={(e) => onChange({ ...value, [key]: e.target.value })}
              className={fieldClasses}
            />
          ) : (
            <input
              key={key}
              value={value[key] ?? ""}
              placeholder={localeLabel}
              onChange={(e) => onChange({ ...value, [key]: e.target.value })}
              className={fieldClasses}
            />
          ),
        )}
      </div>
    </div>
  );
}

function TranslatedListInput({
  label,
  value,
  onChange,
  hint,
}: {
  label: string;
  value: TranslatedListState;
  onChange: (next: TranslatedListState) => void;
  hint?: string;
}) {
  return (
    <div className="space-y-1.5">
      <p className={labelClasses}>
        {label}
        {hint ? <span className="ml-1.5 font-normal text-ink-faint">({hint})</span> : null}
      </p>
      <div className="grid gap-2 sm:grid-cols-3">
        {LOCALES.map(({ key, label: localeLabel }) => (
          <textarea
            key={key}
            value={value[key] ?? ""}
            placeholder={`${localeLabel}\nմեկ տող = մեկ կետ`}
            rows={3}
            onChange={(e) => onChange({ ...value, [key]: e.target.value })}
            className={fieldClasses}
          />
        ))}
      </div>
    </div>
  );
}

const TABS = ["basic", "application", "seo", "media", "relations"] as const;
type Tab = (typeof TABS)[number];
const TAB_LABELS: Record<Tab, string> = {
  basic: "Հիմնական",
  application: "Կիրառում և անվտանգություն",
  seo: "SEO",
  media: "Նկարներ",
  relations: "Կապեր",
};

export function ProductEditor({
  item,
  categories,
  services,
  allProducts,
}: {
  item?: AdminProductDto;
  categories: { id: number; name: string }[];
  services: { id: number; name: string }[];
  allProducts: { id: number; name: string }[];
}) {
  const router = useRouter();
  const isEdit = Boolean(item);
  const [form, setForm] = useState<FormState>(() => toFormState(item));
  const [tab, setTab] = useState<Tab>("basic");
  const [pending, setPending] = useState(false);
  const [errors, setErrors] = useState<Record<string, string[]> | null>(null);

  function set<K extends keyof FormState>(key: K, value: FormState[K]) {
    setForm((f) => ({ ...f, [key]: value }));
  }

  const otherProducts = useMemo(() => allProducts.filter((p) => p.id !== item?.id), [allProducts, item?.id]);

  const seoSnippetUrl = useMemo(() => {
    const category = categories.find((c) => c.id === form.primaryCategoryId);
    return `servio.am/hy/market/${category ? "..." : "kategoria"}/${form.slugHy || "slug"}`;
  }, [categories, form.primaryCategoryId, form.slugHy]);

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault();
    setPending(true);
    setErrors(null);

    try {
      const response = await fetch(isEdit ? `/api/admin/products/${item!.id}` : "/api/admin/products", {
        method: isEdit ? "PATCH" : "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(buildPayload(form)),
      });

      if (!response.ok) {
        const body = await response.json().catch(() => null);
        setErrors(body?.errors ?? { _: ["Չհաջողվեց պահպանել։"] });
        return;
      }

      router.push("/admin/products");
      router.refresh();
    } finally {
      setPending(false);
    }
  }

  function toggleInArray(key: "categoryIds" | "serviceIds" | "relatedProductIds" | "accessoryProductIds", id: number) {
    setForm((f) => {
      const current = f[key];
      const next = current.includes(id) ? current.filter((x) => x !== id) : [...current, id];
      return { ...f, [key]: next };
    });
  }

  return (
    <form onSubmit={handleSubmit} className="space-y-6">
      <div className="flex flex-wrap gap-2 border-b border-border pb-3">
        {TABS.map((key) => (
          <button
            key={key}
            type="button"
            onClick={() => setTab(key)}
            className={`rounded-full px-3.5 py-1.5 text-xs font-medium ${
              tab === key ? "bg-primary text-primary-foreground" : "bg-surface-secondary text-ink-muted hover:text-ink"
            }`}
          >
            {TAB_LABELS[key]}
          </button>
        ))}
      </div>

      {errors ? (
        <div className="rounded-md border border-danger bg-danger-light p-3 text-xs text-danger">
          {Object.entries(errors).map(([field, messages]) => (
            <p key={field}>
              <strong>{field}:</strong> {messages.join(" ")}
            </p>
          ))}
        </div>
      ) : null}

      {tab === "basic" ? (
        <div className={sectionClasses}>
          <div className="grid gap-2 sm:grid-cols-3">
            <div className="space-y-1.5">
              <p className={labelClasses}>Slug (hy)</p>
              <input value={form.slugHy} onChange={(e) => set("slugHy", e.target.value)} required className={fieldClasses} />
            </div>
            <div className="space-y-1.5">
              <p className={labelClasses}>Slug (ru)</p>
              <input value={form.slugRu} onChange={(e) => set("slugRu", e.target.value)} required className={fieldClasses} />
            </div>
            <div className="space-y-1.5">
              <p className={labelClasses}>Slug (en)</p>
              <input value={form.slugEn} onChange={(e) => set("slugEn", e.target.value)} required className={fieldClasses} />
            </div>
          </div>

          <div className="grid gap-2 sm:grid-cols-4">
            <div className="space-y-1.5">
              <p className={labelClasses}>Կարգավիճակ</p>
              <select value={form.status} onChange={(e) => set("status", e.target.value as AdminProductStatus)} className={fieldClasses}>
                {STATUSES.map((s) => (
                  <option key={s} value={s}>
                    {s}
                  </option>
                ))}
              </select>
            </div>
            <div className="space-y-1.5">
              <p className={labelClasses}>Գին (֏)</p>
              <input type="number" value={form.priceAmount} onChange={(e) => set("priceAmount", e.target.value)} className={fieldClasses} />
            </div>
            <div className="space-y-1.5">
              <p className={labelClasses}>Հին գին (֏)</p>
              <input type="number" value={form.oldPriceAmount} onChange={(e) => set("oldPriceAmount", e.target.value)} className={fieldClasses} />
            </div>
            <div className="space-y-1.5">
              <p className={labelClasses}>Կարգ (sort)</p>
              <input type="number" value={form.sortOrder} onChange={(e) => set("sortOrder", e.target.value)} className={fieldClasses} />
            </div>
          </div>

          <TranslatedInput label="Անվանում" value={form.name} onChange={(v) => set("name", v)} />
          <TranslatedInput label="Կարճ նկարագրություն" value={form.shortDescription} onChange={(v) => set("shortDescription", v)} multiline />
          <TranslatedInput label="Ամբողջական նկարագրություն" value={form.description} onChange={(v) => set("description", v)} multiline rows={5} />

          <div className="grid gap-2 sm:grid-cols-4">
            <div className="space-y-1.5">
              <p className={labelClasses}>Բրենդ</p>
              <input value={form.brand} onChange={(e) => set("brand", e.target.value)} className={fieldClasses} />
            </div>
            <div className="space-y-1.5">
              <p className={labelClasses}>Արտադրող</p>
              <input value={form.manufacturer} onChange={(e) => set("manufacturer", e.target.value)} className={fieldClasses} />
            </div>
            <div className="space-y-1.5">
              <p className={labelClasses}>Երկիր</p>
              <input value={form.countryOfOrigin} onChange={(e) => set("countryOfOrigin", e.target.value)} className={fieldClasses} />
            </div>
            <div className="space-y-1.5">
              <p className={labelClasses}>SKU</p>
              <input value={form.sku} onChange={(e) => set("sku", e.target.value)} className={fieldClasses} />
            </div>
            <div className="space-y-1.5">
              <p className={labelClasses}>GTIN/EAN</p>
              <input value={form.gtin} onChange={(e) => set("gtin", e.target.value)} className={fieldClasses} />
            </div>
            <div className="space-y-1.5">
              <p className={labelClasses}>Ծավալ</p>
              <input value={form.volumeValue} onChange={(e) => set("volumeValue", e.target.value)} className={fieldClasses} />
            </div>
            <div className="space-y-1.5">
              <p className={labelClasses}>Չափման միավոր</p>
              <input value={form.volumeUnit} placeholder="լ, մլ, կգ..." onChange={(e) => set("volumeUnit", e.target.value)} className={fieldClasses} />
            </div>
            <div className="space-y-1.5">
              <p className={labelClasses}>Պաշարի քանակ</p>
              <input type="number" value={form.stockQuantity} onChange={(e) => set("stockQuantity", e.target.value)} className={fieldClasses} />
            </div>
          </div>

          <div className="space-y-1.5">
            <p className={labelClasses}>Առկայության կարգավիճակ</p>
            <select value={form.availabilityStatus} onChange={(e) => set("availabilityStatus", e.target.value)} className={`${fieldClasses} w-auto`}>
              {AVAILABILITY_STATUSES.map((s) => (
                <option key={s} value={s}>
                  {s || "—"}
                </option>
              ))}
            </select>
          </div>

          <div className="space-y-1.5">
            <p className={labelClasses}>Կատեգորիաներ</p>
            <div className="flex flex-wrap gap-2">
              {categories.map((c) => (
                <label
                  key={c.id}
                  className={pillLabelClasses(form.categoryIds.includes(c.id))}
                >
                  <input type="checkbox" className="sr-only" checked={form.categoryIds.includes(c.id)} onChange={() => toggleInArray("categoryIds", c.id)} />
                  {c.name}
                </label>
              ))}
            </div>
          </div>

          {form.categoryIds.length > 0 ? (
            <div className="space-y-1.5">
              <p className={labelClasses}>Հիմնական կատեգորիա (canonical URL-ի համար)</p>
              <select
                value={form.primaryCategoryId ?? ""}
                onChange={(e) => set("primaryCategoryId", e.target.value ? Number(e.target.value) : null)}
                className={`${fieldClasses} w-auto`}
              >
                <option value="">—</option>
                {categories
                  .filter((c) => form.categoryIds.includes(c.id))
                  .map((c) => (
                    <option key={c.id} value={c.id}>
                      {c.name}
                    </option>
                  ))}
              </select>
            </div>
          ) : null}

          <div className="space-y-1.5">
            <p className={labelClasses}>Ներքին նշում ադմինի համար (հրապարակային չէ)</p>
            <textarea value={form.adminNote} onChange={(e) => set("adminNote", e.target.value)} rows={2} className={fieldClasses} />
          </div>
        </div>
      ) : null}

      {tab === "application" ? (
        <div className={sectionClasses}>
          <TranslatedListInput label="Հարմար է հետևյալ բծերի համար" value={form.stains} onChange={(v) => set("stains", v)} />
          <TranslatedListInput label="Համապատասխան մակերեսներ/տեքստիլ" value={form.surfaces} onChange={(v) => set("surfaces", v)} />
          <TranslatedListInput label="Որտեղ չի կարելի կիրառել" value={form.forbiddenSurfaces} onChange={(v) => set("forbiddenSurfaces", v)} />

          <div className="grid gap-2 sm:grid-cols-2">
            <div className="space-y-1.5">
              <p className={labelClasses}>pH մակարդակ</p>
              <input value={form.phValue} onChange={(e) => set("phValue", e.target.value)} className={fieldClasses} />
            </div>
            <div className="space-y-1.5">
              <p className={labelClasses}>Բաղադրության տեսակ</p>
              <select value={form.compositionType} onChange={(e) => set("compositionType", e.target.value)} className={fieldClasses}>
                {COMPOSITION_TYPES.map((c) => (
                  <option key={c} value={c}>
                    {c || "—"}
                  </option>
                ))}
              </select>
            </div>
          </div>

          <TranslatedListInput
            label="Նոսրացման աղյուսակ"
            value={form.dilutionTable}
            onChange={(v) => set("dilutionTable", v)}
            hint="ֆորմատ. 1:10 — կիրառություն"
          />
          <TranslatedListInput label="Կիրառման քայլեր" value={form.usageSteps} onChange={(v) => set("usageSteps", v)} />

          <label className="flex items-center gap-2 text-sm text-ink">
            <input type="checkbox" checked={form.requiresExtractor} onChange={(e) => set("requiresExtractor", e.target.checked)} />
            Անհրաժեշտ է էքստրակտոր/մոծիկ փոշեկուլ
          </label>

          <TranslatedInput label="Մոտավոր ծախս" value={form.consumptionNote} onChange={(v) => set("consumptionNote", v)} />
          <TranslatedInput label="Ազդեցության ժամանակ" value={form.contactTime} onChange={(v) => set("contactTime", v)} />
          <TranslatedInput label="Ողողման եղանակ" value={form.rinseMethod} onChange={(v) => set("rinseMethod", v)} />
          <TranslatedListInput label="Անվտանգության միջոցներ" value={form.safetyNotes} onChange={(v) => set("safetyNotes", v)} />
          <TranslatedInput label="Պահպանման պայմաններ" value={form.storageConditions} onChange={(v) => set("storageConditions", v)} />
          <TranslatedInput label="Պիտանելիության ժամկետ" value={form.shelfLife} onChange={(v) => set("shelfLife", v)} />

          <label className="flex items-center gap-2 text-sm text-ink">
            <input type="checkbox" checked={form.batchTracked} onChange={(e) => set("batchTracked", e.target.checked)} />
            Հաշվառվում է խմբաքանակով (batch)
          </label>

          <div className="space-y-1.5">
            <p className={labelClasses}>SDS ֆայլի հղում</p>
            <input value={form.sdsUrl} onChange={(e) => set("sdsUrl", e.target.value)} className={fieldClasses} />
          </div>
          <TranslatedInput label="Սերտիֆիկատի մասին տեղեկություն" value={form.certificateNote} onChange={(v) => set("certificateNote", v)} />
        </div>
      ) : null}

      {tab === "seo" ? (
        <div className={sectionClasses}>
          <div className="rounded-md border border-border bg-surface-secondary p-3 text-xs text-ink-muted">
            <p className="font-medium text-ink">Որոնման նախադիտում (Google snippet)</p>
            <p className="mt-1 truncate text-primary">{seoSnippetUrl}</p>
            <p className="mt-0.5 truncate text-[13px] text-[#1a0dab]">{form.seoTitle.hy || form.name.hy || "Վերնագիր"}</p>
            <p className="mt-0.5 line-clamp-2 text-ink-muted">{form.seoDescription.hy || form.shortDescription.hy || "Նկարագրություն"}</p>
            <p className="mt-1 text-[11px] text-ink-faint">
              Եթե SEO դաշտերը դատարկ են, համակարգը ավտոմատ կգեներացնի անվան, բրենդի և կատեգորիայի հիման վրա։
            </p>
          </div>

          <TranslatedInput label="SEO վերնագիր" value={form.seoTitle} onChange={(v) => set("seoTitle", v)} />
          <TranslatedInput label="SEO նկարագրություն" value={form.seoDescription} onChange={(v) => set("seoDescription", v)} multiline />
          <TranslatedInput label="Open Graph վերնագիր" value={form.ogTitle} onChange={(v) => set("ogTitle", v)} />
          <TranslatedInput label="Open Graph նկարագրություն" value={form.ogDescription} onChange={(v) => set("ogDescription", v)} multiline />

          <div className="grid gap-2 sm:grid-cols-2">
            <div className="space-y-1.5">
              <p className={labelClasses}>OG նկարի ուղի</p>
              <input value={form.ogImage} onChange={(e) => set("ogImage", e.target.value)} className={fieldClasses} />
            </div>
            <div className="space-y-1.5">
              <p className={labelClasses}>Canonical override</p>
              <input value={form.canonicalOverride} onChange={(e) => set("canonicalOverride", e.target.value)} className={fieldClasses} />
            </div>
            <div className="space-y-1.5">
              <p className={labelClasses}>Robots</p>
              <select value={form.seoRobots} onChange={(e) => set("seoRobots", e.target.value as "index" | "noindex")} className={fieldClasses}>
                {SEO_ROBOTS.map((r) => (
                  <option key={r} value={r}>
                    {r}
                  </option>
                ))}
              </select>
            </div>
            <div className="space-y-1.5">
              <p className={labelClasses}>Հիմնական հարցում</p>
              <input value={form.primaryKeyword} onChange={(e) => set("primaryKeyword", e.target.value)} className={fieldClasses} />
            </div>
          </div>

          <div className="space-y-1.5">
            <p className={labelClasses}>Լրացուցիչ հարցումներ (մեկ տող = մեկ հարցում)</p>
            <textarea value={form.secondaryKeywords} onChange={(e) => set("secondaryKeywords", e.target.value)} rows={3} className={fieldClasses} />
          </div>
        </div>
      ) : null}

      {tab === "media" ? (
        <div className={sectionClasses}>
          <div className="space-y-1.5">
            <p className={labelClasses}>Հիմնական նկարի ուղի</p>
            <input value={form.image} onChange={(e) => set("image", e.target.value)} placeholder="/images/products/example.webp" className={fieldClasses} />
          </div>
          <TranslatedInput label="Alt տեքստ" value={form.alt} onChange={(v) => set("alt", v)} />
          <p className="text-xs text-ink-faint">
            Բազմաթիվ նկարների կառավարումը (գալերիա) կպահանջի իրական ֆայլեր public/images/products տակ։ Առայժմ
            օգտագործեք վերևի հիմնական ուղին. պատկերասրահի endpoint-ը հասանելի է API-ում ապագա օգտագործման համար։
          </p>
        </div>
      ) : null}

      {tab === "relations" ? (
        <div className={sectionClasses}>
          <div className="space-y-1.5">
            <p className={labelClasses}>Առնչվող Servio ծառայություններ</p>
            <div className="flex flex-wrap gap-2">
              {services.map((s) => (
                <label
                  key={s.id}
                  className={pillLabelClasses(form.serviceIds.includes(s.id))}
                >
                  <input type="checkbox" className="sr-only" checked={form.serviceIds.includes(s.id)} onChange={() => toggleInArray("serviceIds", s.id)} />
                  {s.name}
                </label>
              ))}
            </div>
          </div>

          <div className="space-y-1.5">
            <p className={labelClasses}>Նմանատիպ ապրանքներ (related)</p>
            <div className="flex max-h-48 flex-wrap gap-2 overflow-y-auto">
              {otherProducts.map((p) => (
                <label
                  key={p.id}
                  className={pillLabelClasses(form.relatedProductIds.includes(p.id))}
                >
                  <input
                    type="checkbox"
                    className="sr-only"
                    checked={form.relatedProductIds.includes(p.id)}
                    onChange={() => toggleInArray("relatedProductIds", p.id)}
                  />
                  {p.name}
                </label>
              ))}
            </div>
          </div>

          <div className="space-y-1.5">
            <p className={labelClasses}>Համապատասխան պարագաներ/աքսեսուարներ</p>
            <div className="flex max-h-48 flex-wrap gap-2 overflow-y-auto">
              {otherProducts.map((p) => (
                <label
                  key={p.id}
                  className={pillLabelClasses(form.accessoryProductIds.includes(p.id))}
                >
                  <input
                    type="checkbox"
                    className="sr-only"
                    checked={form.accessoryProductIds.includes(p.id)}
                    onChange={() => toggleInArray("accessoryProductIds", p.id)}
                  />
                  {p.name}
                </label>
              ))}
            </div>
          </div>
        </div>
      ) : null}

      <div className="flex items-center gap-3 border-t border-border pt-4">
        <button type="submit" disabled={pending} className="rounded-md bg-primary px-5 py-2.5 text-sm font-medium text-primary-foreground hover:bg-primary-hover disabled:opacity-40">
          {pending ? "Պահպանվում է…" : "Պահպանել"}
        </button>
        <Link href="/admin/products" className="text-sm text-ink-muted hover:text-ink">
          Չեղարկել
        </Link>
      </div>
    </form>
  );
}
