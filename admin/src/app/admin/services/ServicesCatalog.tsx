"use client";

import { useState } from "react";
import { useRouter } from "next/navigation";
import type { ServicePriceType, ServiceStatus } from "@/lib/api";
import type { AdminServiceCategoryDto, AdminServiceDto, AdminTranslated } from "@/lib/adminApi";
import { ImageUploadField } from "./ImageUploadField";
import { TranslatedFields, emptyTranslated, toTranslated, fieldClasses, labelClasses } from "./shared";
import { Badge, Button, EmptyCard } from "../_components/ui";

const STATUS_LABELS: Record<ServiceStatus, string> = {
  draft: "Սևագիր",
  active: "Ակտիվ",
  coming_soon: "Շուտով",
  temporarily_unavailable: "Ժամանակավոր անհասանելի",
  archived: "Արխիվ",
};

// "By car type" is set in the detailed editor (/admin/services/details);
// a service priced that way keeps it here.
const PRICE_TYPE_LABELS: Partial<Record<ServicePriceType, string>> = {
  fixed: "Ֆիքսված գին",
  from: "Սկսած",
  range: "Միջակայք",
  on_inspection: "Զննումից հետո",
};

type Form = {
  name: AdminTranslated;
  shortDescription: AdminTranslated;
  description: AdminTranslated;
  categoryId: string;
  status: ServiceStatus;
  priceType: ServicePriceType | "";
  priceAmount: string;
  priceMin: string;
  priceMax: string;
  image: string;
};

function formFrom(service: AdminServiceDto | null, defaultCategoryId: string): Form {
  return {
    name: service ? toTranslated(service.name) : emptyTranslated(),
    shortDescription: service ? toTranslated(service.shortDescription) : emptyTranslated(),
    description: service ? toTranslated(service.description) : emptyTranslated(),
    categoryId: service ? String(service.categoryId) : defaultCategoryId,
    status: service?.status ?? "draft",
    priceType: service?.priceType ?? "",
    priceAmount: service?.priceAmount?.toString() ?? "",
    priceMin: service?.priceMin?.toString() ?? "",
    priceMax: service?.priceMax?.toString() ?? "",
    image: service?.image ?? "",
  };
}

const RU_TO_LATIN: Record<string, string> = {
  а: "a", б: "b", в: "v", г: "g", д: "d", е: "e", ё: "e", ж: "zh", з: "z", и: "i", й: "y", к: "k", л: "l",
  м: "m", н: "n", о: "o", п: "p", р: "r", с: "s", т: "t", у: "u", ф: "f", х: "h", ц: "ts", ч: "ch", ш: "sh",
  щ: "sch", ъ: "", ы: "y", ь: "", э: "e", ю: "yu", я: "ya",
};

function slugify(text: string): string {
  return [...text.toLowerCase()]
    .map((ch) => RU_TO_LATIN[ch] ?? ch)
    .join("")
    .replace(/[^a-z0-9]+/g, "-")
    .replace(/^-+|-+$/g, "")
    .slice(0, 80);
}

/** URL slug from the English or Russian name; Armenian-only names get a short unique one. */
function makeSlug(name: AdminTranslated): string {
  return slugify(name.en ?? "") || slugify(name.ru ?? "") || `service-${Date.now().toString(36)}`;
}

const isFilled = (value: AdminTranslated) => (["hy", "ru", "en"] as const).every((l) => (value[l] ?? "").trim() !== "");

/**
 * The API stores every text in hy/ru/en. A draft may have only Armenian
 * (the other names fall back to it); publishing needs all three, so a
 * half-translated service never reaches the site.
 */
function payloadFrom(form: Form) {
  const name = { ...form.name };
  for (const l of ["ru", "en"] as const) if (!(name[l] ?? "").trim()) name[l] = name.hy ?? "";

  const description = { ...form.description };
  for (const l of ["hy", "ru", "en"] as const) {
    if (!(description[l] ?? "").trim()) description[l] = form.shortDescription[l] ?? "";
  }

  const toInt = (v: string) => (v.trim() === "" ? null : Math.max(0, Math.round(Number(v))));

  return {
    name,
    short_description: form.shortDescription,
    description,
    category_id: Number(form.categoryId),
    status: form.status,
    image: form.image.trim() || null,
    price_type: form.priceType || null,
    // By car type: whatever the detailed editor set stays as it is.
    price_amount: ["fixed", "from", "by_car_type"].includes(form.priceType) ? toInt(form.priceAmount) : null,
    price_min: ["range", "by_car_type"].includes(form.priceType) ? toInt(form.priceMin) : null,
    price_max: ["range", "by_car_type"].includes(form.priceType) ? toInt(form.priceMax) : null,
  };
}

function validate(form: Form): string | null {
  if (!(form.name.hy ?? "").trim()) return "Լրացրեք անվանումը հայերեն։";
  if (form.status === "active" && !(isFilled(form.name) && isFilled(form.shortDescription))) {
    return "Կայքում հրապարակելու համար լրացրեք անվանումն ու կարճ նկարագրությունը երեք լեզվով։";
  }
  return null;
}

function priceText(service: AdminServiceDto): string {
  const amd = (n: number | null) => (n == null ? "" : `${n.toLocaleString("en-US")} դր.`);
  switch (service.priceType) {
    case "fixed":
      return amd(service.priceAmount);
    case "from":
      return `${amd(service.priceAmount)}-ից`;
    case "range":
      return `${amd(service.priceMin)} – ${amd(service.priceMax)}`;
    case "on_inspection":
      return "Զննումից հետո";
    case "by_car_type":
      return "Ըստ մեքենայի տեսակի";
    default:
      return "Գին նշված չէ";
  }
}

const DEFAULT_CATEGORY = { slug: "services", name: { hy: "Ծառայություններ", ru: "Услуги", en: "Services" } };

/** A business with no category yet gets a default one, so its first service needs no extra step. */
async function ensureCategory(categories: AdminServiceCategoryDto[], chosen: string): Promise<string | null> {
  if (chosen) return chosen;
  if (categories.length > 0) return String(categories[0].id);

  const response = await fetch("/api/admin/service-categories", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ ...DEFAULT_CATEGORY, sort_order: 0 }),
  });
  if (!response.ok) return null;
  const json = (await response.json()) as { data: AdminServiceCategoryDto };
  return String(json.data.id);
}

export function ServicesCatalog({
  services,
  categories,
}: {
  services: AdminServiceDto[];
  categories: AdminServiceCategoryDto[];
}) {
  const router = useRouter();
  const [editingId, setEditingId] = useState<number | "new" | null>(null);
  const categoryName = (id: number) => categories.find((c) => c.id === id)?.name.hy ?? "";

  return (
    <div className="space-y-3">
      {services.length === 0 && editingId !== "new" ? (
        <EmptyCard>Ծառայություններ դեռ չկան։ Ավելացրեք առաջինը։</EmptyCard>
      ) : null}

      {services.map((service) =>
        editingId === service.id ? (
          <ServiceEditor
            key={service.id}
            service={service}
            categories={categories}
            onDone={() => {
              setEditingId(null);
              router.refresh();
            }}
            onCancel={() => setEditingId(null)}
          />
        ) : (
          <div
            key={service.id}
            className="flex flex-wrap items-center gap-3 rounded-lg border border-border bg-surface px-4 py-3"
          >
            <div className="min-w-0 flex-1">
              <p className="truncate text-sm font-medium text-ink">{service.name.hy || service.slug}</p>
              <p className="truncate text-xs text-ink-muted">
                {[categoryName(service.categoryId), priceText(service)].filter(Boolean).join(" · ")}
              </p>
            </div>
            <Badge tone={service.status === "active" ? "success" : "default"}>{STATUS_LABELS[service.status]}</Badge>
            <Button size="sm" variant="secondary" onClick={() => setEditingId(service.id)}>
              Խմբագրել
            </Button>
          </div>
        ),
      )}

      {editingId === "new" ? (
        <ServiceEditor
          service={null}
          categories={categories}
          onDone={() => {
            setEditingId(null);
            router.refresh();
          }}
          onCancel={() => setEditingId(null)}
        />
      ) : (
        <Button onClick={() => setEditingId("new")}>Ավելացնել ծառայություն</Button>
      )}
    </div>
  );
}

function ServiceEditor({
  service,
  categories,
  onDone,
  onCancel,
}: {
  service: AdminServiceDto | null;
  categories: AdminServiceCategoryDto[];
  onDone: () => void;
  onCancel: () => void;
}) {
  const [form, setForm] = useState<Form>(() => formFrom(service, categories[0] ? String(categories[0].id) : ""));
  const [pending, setPending] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const set = <K extends keyof Form>(key: K, value: Form[K]) => setForm((f) => ({ ...f, [key]: value }));

  async function save(e: React.FormEvent) {
    e.preventDefault();
    const invalid = validate(form);
    if (invalid) {
      setError(invalid);
      return;
    }
    setPending(true);
    setError(null);
    try {
      const categoryId = await ensureCategory(categories, form.categoryId);
      if (!categoryId) {
        setError("Չհաջողվեց ստեղծել կատեգորիա։");
        return;
      }
      const payload = { ...payloadFrom(form), category_id: Number(categoryId) };
      const response = service
        ? await fetch(`/api/admin/services/${service.id}`, {
            method: "PATCH",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify(payload),
          })
        : await fetch("/api/admin/services", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ ...payload, slug: makeSlug(form.name), sort_order: 0 }),
          });
      if (!response.ok) {
        setError("Չհաջողվեց պահպանել։ Ստուգեք դաշտերը։");
        return;
      }
      onDone();
    } catch {
      setError("Չհաջողվեց պահպանել։");
    } finally {
      setPending(false);
    }
  }

  async function remove() {
    if (!service || !confirm(`Ջնջե՞լ «${service.name.hy}» ծառայությունը։`)) return;
    setPending(true);
    try {
      const response = await fetch(`/api/admin/services/${service.id}`, { method: "DELETE" });
      if (response.ok) onDone();
      else setError("Չհաջողվեց ջնջել։");
    } finally {
      setPending(false);
    }
  }

  return (
    <form onSubmit={save} className="space-y-4 rounded-lg border border-primary/40 bg-surface p-4">
      <TranslatedFields label="Անվանում" value={form.name} onChange={(v) => set("name", v)} />
      <TranslatedFields
        label="Կարճ նկարագրություն"
        value={form.shortDescription}
        onChange={(v) => set("shortDescription", v)}
        multiline
      />
      <TranslatedFields
        label="Մանրամասն նկարագրություն (ոչ պարտադիր)"
        value={form.description}
        onChange={(v) => set("description", v)}
        multiline
      />

      <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <label className="space-y-1.5">
          <span className={labelClasses}>Կատեգորիա</span>
          <select value={form.categoryId} onChange={(e) => set("categoryId", e.target.value)} className={fieldClasses}>
            {categories.length === 0 ? <option value="">Ծառայություններ (կստեղծվի)</option> : null}
            {categories.map((c) => (
              <option key={c.id} value={c.id}>
                {c.name.hy ?? c.slug}
              </option>
            ))}
          </select>
        </label>
        <label className="space-y-1.5">
          <span className={labelClasses}>Կարգավիճակ</span>
          <select
            value={form.status}
            onChange={(e) => set("status", e.target.value as ServiceStatus)}
            className={fieldClasses}
          >
            {(Object.keys(STATUS_LABELS) as ServiceStatus[]).map((s) => (
              <option key={s} value={s}>
                {STATUS_LABELS[s]}
              </option>
            ))}
          </select>
        </label>
        <label className="space-y-1.5">
          <span className={labelClasses}>Գին</span>
          <select
            value={form.priceType}
            onChange={(e) => set("priceType", e.target.value as ServicePriceType | "")}
            className={fieldClasses}
          >
            <option value="">Չնշել</option>
            {Object.entries(PRICE_TYPE_LABELS).map(([value, label]) => (
              <option key={value} value={value}>
                {label}
              </option>
            ))}
            {form.priceType === "by_car_type" ? <option value="by_car_type">Ըստ մեքենայի տեսակի</option> : null}
          </select>
        </label>
        {form.priceType === "fixed" || form.priceType === "from" ? (
          <label className="space-y-1.5">
            <span className={labelClasses}>Գումար (դր.)</span>
            <input
              type="number"
              min={0}
              value={form.priceAmount}
              onChange={(e) => set("priceAmount", e.target.value)}
              className={fieldClasses}
            />
          </label>
        ) : null}
        {form.priceType === "range" ? (
          <div className="flex gap-2">
            <label className="flex-1 space-y-1.5">
              <span className={labelClasses}>Սկսած (դր.)</span>
              <input
                type="number"
                min={0}
                value={form.priceMin}
                onChange={(e) => set("priceMin", e.target.value)}
                className={fieldClasses}
              />
            </label>
            <label className="flex-1 space-y-1.5">
              <span className={labelClasses}>Մինչև (դր.)</span>
              <input
                type="number"
                min={0}
                value={form.priceMax}
                onChange={(e) => set("priceMax", e.target.value)}
                className={fieldClasses}
              />
            </label>
          </div>
        ) : null}
      </div>

      <ImageUploadField label="Նկար" value={form.image} onChange={(v) => set("image", v)} folder="services" />

      {error ? <p className="text-sm text-danger">{error}</p> : null}

      <div className="flex flex-wrap gap-2">
        <Button type="submit" disabled={pending}>
          {service ? "Պահպանել" : "Ավելացնել"}
        </Button>
        <Button type="button" variant="ghost" disabled={pending} onClick={onCancel}>
          Չեղարկել
        </Button>
        {service ? (
          <Button type="button" variant="secondary" disabled={pending} onClick={remove} className="ml-auto">
            Ջնջել
          </Button>
        ) : null}
      </div>
    </form>
  );
}
