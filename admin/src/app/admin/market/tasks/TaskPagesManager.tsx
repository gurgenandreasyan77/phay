"use client";

import { useState } from "react";
import { useRouter } from "next/navigation";
import type { AdminMarketTaskPageDto, AdminMarketTaskPageStatus, AdminTranslated } from "@/lib/adminApi";

type Locale = "hy" | "ru" | "en";
const LOCALES: { key: Locale; label: string }[] = [
  { key: "hy", label: "Հայերեն" },
  { key: "ru", label: "Ռուսերեն" },
  { key: "en", label: "Անգլերեն" },
];
const STATUSES: AdminMarketTaskPageStatus[] = ["draft", "published", "archived"];

const fieldClasses =
  "w-full rounded-md border border-border bg-surface px-2.5 py-1.5 text-sm text-ink placeholder:text-ink-faint transition-colors focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary-light disabled:cursor-not-allowed disabled:bg-surface-secondary disabled:text-ink-faint";
const labelClasses = "text-xs font-medium text-ink-muted";

function toTextareaValue(list: unknown): string {
  return Array.isArray(list) ? list.filter((x) => typeof x === "string").join("\n") : "";
}
function fromTextareaValue(value: string): string[] {
  return value.split("\n").map((line) => line.trim()).filter(Boolean);
}

type TranslatedListState = Record<Locale, string>;

type FormState = {
  slugHy: string;
  slugRu: string;
  slugEn: string;
  status: AdminMarketTaskPageStatus;
  sortOrder: string;
  title: AdminTranslated;
  intro: AdminTranslated;
  selectionRules: TranslatedListState;
  precautions: TranslatedListState;
  seoTitle: AdminTranslated;
  seoDescription: AdminTranslated;
  relatedServiceId: number | null;
  productIds: number[];
};

function toFormState(item?: AdminMarketTaskPageDto): FormState {
  return {
    slugHy: item?.slugHy ?? "",
    slugRu: item?.slugRu ?? "",
    slugEn: item?.slugEn ?? "",
    status: item?.status ?? "draft",
    sortOrder: item?.sortOrder?.toString() ?? "0",
    title: { hy: item?.title.hy ?? "", ru: item?.title.ru ?? "", en: item?.title.en ?? "" },
    intro: { hy: item?.intro.hy ?? "", ru: item?.intro.ru ?? "", en: item?.intro.en ?? "" },
    selectionRules: {
      hy: toTextareaValue((item?.selectionRules as Record<string, unknown>)?.hy),
      ru: toTextareaValue((item?.selectionRules as Record<string, unknown>)?.ru),
      en: toTextareaValue((item?.selectionRules as Record<string, unknown>)?.en),
    },
    precautions: {
      hy: toTextareaValue((item?.precautions as Record<string, unknown>)?.hy),
      ru: toTextareaValue((item?.precautions as Record<string, unknown>)?.ru),
      en: toTextareaValue((item?.precautions as Record<string, unknown>)?.en),
    },
    seoTitle: { hy: item?.seoTitle.hy ?? "", ru: item?.seoTitle.ru ?? "", en: item?.seoTitle.en ?? "" },
    seoDescription: { hy: item?.seoDescription.hy ?? "", ru: item?.seoDescription.ru ?? "", en: item?.seoDescription.en ?? "" },
    relatedServiceId: item?.relatedServiceId ?? null,
    productIds: item?.productIds ?? [],
  };
}

function buildPayload(form: FormState) {
  return {
    slug_hy: form.slugHy.trim(),
    slug_ru: form.slugRu.trim(),
    slug_en: form.slugEn.trim(),
    status: form.status,
    sort_order: Number(form.sortOrder) || 0,
    title: form.title,
    intro: form.intro,
    selection_rules: {
      hy: fromTextareaValue(form.selectionRules.hy),
      ru: fromTextareaValue(form.selectionRules.ru),
      en: fromTextareaValue(form.selectionRules.en),
    },
    precautions: {
      hy: fromTextareaValue(form.precautions.hy),
      ru: fromTextareaValue(form.precautions.ru),
      en: fromTextareaValue(form.precautions.en),
    },
    seo_title: form.seoTitle,
    seo_description: form.seoDescription,
    related_service_id: form.relatedServiceId,
    product_ids: form.productIds,
  };
}

function TranslatedFields({
  label,
  value,
  onChange,
  multiline = false,
}: {
  label: string;
  value: AdminTranslated;
  onChange: (next: AdminTranslated) => void;
  multiline?: boolean;
}) {
  return (
    <div className="space-y-1.5">
      <p className={labelClasses}>{label}</p>
      <div className="grid gap-2 sm:grid-cols-3">
        {LOCALES.map(({ key, label: localeLabel }) =>
          multiline ? (
            <textarea key={key} value={value[key] ?? ""} placeholder={localeLabel} rows={3} onChange={(e) => onChange({ ...value, [key]: e.target.value })} className={fieldClasses} />
          ) : (
            <input key={key} value={value[key] ?? ""} placeholder={localeLabel} onChange={(e) => onChange({ ...value, [key]: e.target.value })} className={fieldClasses} />
          ),
        )}
      </div>
    </div>
  );
}

function TranslatedListFields({
  label,
  value,
  onChange,
}: {
  label: string;
  value: TranslatedListState;
  onChange: (next: TranslatedListState) => void;
}) {
  return (
    <div className="space-y-1.5">
      <p className={labelClasses}>{label} (մեկ տող = մեկ կետ)</p>
      <div className="grid gap-2 sm:grid-cols-3">
        {LOCALES.map(({ key, label: localeLabel }) => (
          <textarea key={key} value={value[key] ?? ""} placeholder={localeLabel} rows={3} onChange={(e) => onChange({ ...value, [key]: e.target.value })} className={fieldClasses} />
        ))}
      </div>
    </div>
  );
}

export function TaskPagesManager({
  items,
  products,
  services,
}: {
  items: AdminMarketTaskPageDto[];
  products: { id: number; name: string }[];
  services: { id: number; name: string }[];
}) {
  const router = useRouter();
  const sorted = [...items].sort((a, b) => a.sortOrder - b.sortOrder);
  const [creating, setCreating] = useState(false);

  return (
    <div className="space-y-4">
      {sorted.map((item) => (
        <TaskPageCard key={item.id} item={item} products={products} services={services} onChanged={() => router.refresh()} />
      ))}

      {creating ? (
        <TaskPageForm products={products} services={services} onDone={() => { setCreating(false); router.refresh(); }} onCancel={() => setCreating(false)} />
      ) : (
        <button type="button" onClick={() => setCreating(true)} className="rounded-md border border-border px-4 py-2 text-sm font-medium text-ink hover:bg-surface-secondary">
          + Ավելացնել էջ
        </button>
      )}
    </div>
  );
}

function TaskPageCard({
  item,
  products,
  services,
  onChanged,
}: {
  item: AdminMarketTaskPageDto;
  products: { id: number; name: string }[];
  services: { id: number; name: string }[];
  onChanged: () => void;
}) {
  const [editing, setEditing] = useState(false);

  async function remove() {
    if (!confirm("Ջնջե՞լ այս էջը։")) return;
    const response = await fetch(`/api/admin/task-pages/${item.id}`, { method: "DELETE" });
    if (response.ok) onChanged();
  }

  if (editing) {
    return (
      <TaskPageForm item={item} products={products} services={services} onDone={() => { setEditing(false); onChanged(); }} onCancel={() => setEditing(false)} />
    );
  }

  return (
    <div className="flex items-center justify-between rounded-lg border border-border bg-surface p-4">
      <div>
        <div className="flex items-center gap-2">
          <p className="text-sm font-semibold text-ink">{item.title.hy ?? item.title.ru}</p>
          <span className="rounded-full bg-surface-secondary px-2 py-0.5 text-[10px] font-medium text-ink-muted">{item.status}</span>
        </div>
        <p className="mt-0.5 font-mono text-xs text-ink-muted">/market/tasks/{item.slugHy}</p>
      </div>
      <div className="flex items-center gap-3">
        <button type="button" onClick={() => setEditing(true)} className="text-xs font-medium text-primary hover:underline">
          Խմբագրել
        </button>
        <button type="button" onClick={remove} className="text-xs font-medium text-ink-muted hover:text-danger hover:underline">
          Ջնջել
        </button>
      </div>
    </div>
  );
}

function TaskPageForm({
  item,
  products,
  services,
  onDone,
  onCancel,
}: {
  item?: AdminMarketTaskPageDto;
  products: { id: number; name: string }[];
  services: { id: number; name: string }[];
  onDone: () => void;
  onCancel: () => void;
}) {
  const [form, setForm] = useState<FormState>(() => toFormState(item));
  const [status, setStatus] = useState<"idle" | "saving" | "error">("idle");
  const [errors, setErrors] = useState<Record<string, string[]> | null>(null);

  function set<K extends keyof FormState>(key: K, value: FormState[K]) {
    setForm((f) => ({ ...f, [key]: value }));
  }

  function toggleProduct(id: number) {
    setForm((f) => ({
      ...f,
      productIds: f.productIds.includes(id) ? f.productIds.filter((x) => x !== id) : [...f.productIds, id],
    }));
  }

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault();
    setStatus("saving");
    setErrors(null);

    try {
      const response = await fetch(item ? `/api/admin/task-pages/${item.id}` : "/api/admin/task-pages", {
        method: item ? "PATCH" : "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(buildPayload(form)),
      });

      if (!response.ok) {
        const body = await response.json().catch(() => null);
        setErrors(body?.errors ?? null);
        setStatus("error");
        return;
      }

      onDone();
    } catch {
      setStatus("error");
    }
  }

  return (
    <form onSubmit={handleSubmit} className="space-y-3 rounded-lg border border-primary-light bg-primary-light/30 p-4">
      {errors ? (
        <div className="rounded-md border border-danger bg-danger-light p-3 text-xs text-danger">
          {Object.entries(errors).map(([field, messages]) => (
            <p key={field}>
              <strong>{field}:</strong> {messages.join(" ")}
            </p>
          ))}
        </div>
      ) : null}

      <div className="grid gap-2 sm:grid-cols-4">
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
        <div className="space-y-1.5">
          <p className={labelClasses}>Կարգավիճակ</p>
          <select value={form.status} onChange={(e) => set("status", e.target.value as AdminMarketTaskPageStatus)} className={fieldClasses}>
            {STATUSES.map((s) => (
              <option key={s} value={s}>
                {s}
              </option>
            ))}
          </select>
        </div>
      </div>

      <TranslatedFields label="Վերնագիր" value={form.title} onChange={(v) => set("title", v)} />
      <TranslatedFields label="Ներածություն (intro)" value={form.intro} onChange={(v) => set("intro", v)} multiline />
      <TranslatedListFields label="Ինչպես ընտրել միջոց" value={form.selectionRules} onChange={(v) => set("selectionRules", v)} />
      <TranslatedListFields label="Նախազգուշություններ" value={form.precautions} onChange={(v) => set("precautions", v)} />
      <TranslatedFields label="SEO վերնագիր" value={form.seoTitle} onChange={(v) => set("seoTitle", v)} />
      <TranslatedFields label="SEO նկարագրություն" value={form.seoDescription} onChange={(v) => set("seoDescription", v)} multiline />

      <div className="space-y-1.5">
        <p className={labelClasses}>Առնչվող ծառայություն</p>
        <select
          value={form.relatedServiceId ?? ""}
          onChange={(e) => set("relatedServiceId", e.target.value ? Number(e.target.value) : null)}
          className={`${fieldClasses} w-auto`}
        >
          <option value="">—</option>
          {services.map((s) => (
            <option key={s.id} value={s.id}>
              {s.name}
            </option>
          ))}
        </select>
      </div>

      <div className="space-y-1.5">
        <p className={labelClasses}>Համապատասխան ապրանքներ</p>
        <div className="flex max-h-40 flex-wrap gap-2 overflow-y-auto">
          {products.map((p) => (
            <label
              key={p.id}
              className={`flex cursor-pointer items-center gap-1.5 rounded-full border px-3 py-1.5 text-xs ${
                form.productIds.includes(p.id) ? "border-primary bg-primary-light text-primary" : "border-border text-ink-muted"
              }`}
            >
              <input type="checkbox" className="sr-only" checked={form.productIds.includes(p.id)} onChange={() => toggleProduct(p.id)} />
              {p.name}
            </label>
          ))}
        </div>
      </div>

      <div className="flex items-center gap-3">
        <button type="submit" disabled={status === "saving"} className="rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:bg-primary-hover disabled:opacity-50">
          Պահպանել
        </button>
        <button type="button" onClick={onCancel} className="text-sm text-ink-muted hover:text-ink">
          Չեղարկել
        </button>
        {status === "error" && !errors ? <span className="text-sm text-danger">Չհաջողվեց պահպանել։</span> : null}
      </div>
    </form>
  );
}
