"use client";

import { useState } from "react";
import { useRouter } from "next/navigation";
import type { AdminProductCategoryDto, AdminTranslated } from "@/lib/adminApi";

type Locale = "hy" | "ru" | "en";
const LOCALES: { key: Locale; label: string }[] = [
  { key: "hy", label: "Հայերեն" },
  { key: "ru", label: "Ռուսերեն" },
  { key: "en", label: "Անգլերեն" },
];

const fieldClasses =
  "w-full rounded-md border border-border bg-surface px-2.5 py-1.5 text-sm text-ink placeholder:text-ink-faint transition-colors focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary-light disabled:cursor-not-allowed disabled:bg-surface-secondary disabled:text-ink-faint";
const labelClasses = "text-xs font-medium text-ink-muted";

type FormState = {
  slugHy: string;
  slugRu: string;
  slugEn: string;
  icon: string;
  sortOrder: string;
  name: AdminTranslated;
  shortDescription: AdminTranslated;
};

function toFormState(item?: AdminProductCategoryDto): FormState {
  return {
    slugHy: item?.slugHy ?? "",
    slugRu: item?.slugRu ?? "",
    slugEn: item?.slugEn ?? "",
    icon: item?.icon ?? "",
    sortOrder: item?.sortOrder?.toString() ?? "0",
    name: { hy: item?.name.hy ?? "", ru: item?.name.ru ?? "", en: item?.name.en ?? "" },
    shortDescription: { hy: item?.shortDescription.hy ?? "", ru: item?.shortDescription.ru ?? "", en: item?.shortDescription.en ?? "" },
  };
}

function buildPayload(form: FormState) {
  return {
    slug_hy: form.slugHy.trim(),
    slug_ru: form.slugRu.trim(),
    slug_en: form.slugEn.trim(),
    icon: form.icon.trim() || null,
    sort_order: Number(form.sortOrder) || 0,
    name: form.name,
    short_description: form.shortDescription,
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
            <textarea
              key={key}
              value={value[key] ?? ""}
              placeholder={localeLabel}
              rows={2}
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

export function CategoriesManager({ items }: { items: AdminProductCategoryDto[] }) {
  const router = useRouter();
  const sorted = [...items].sort((a, b) => a.sortOrder - b.sortOrder);
  const [creating, setCreating] = useState(false);

  return (
    <div className="space-y-4">
      {sorted.map((item) => (
        <CategoryCard key={item.id} item={item} onChanged={() => router.refresh()} />
      ))}

      {creating ? (
        <CategoryForm onDone={() => { setCreating(false); router.refresh(); }} onCancel={() => setCreating(false)} />
      ) : (
        <button
          type="button"
          onClick={() => setCreating(true)}
          className="rounded-md border border-border px-4 py-2 text-sm font-medium text-ink hover:bg-surface-secondary"
        >
          + Ավելացնել կատեգորիա
        </button>
      )}
    </div>
  );
}

function CategoryCard({ item, onChanged }: { item: AdminProductCategoryDto; onChanged: () => void }) {
  const [editing, setEditing] = useState(false);

  if (editing) {
    return (
      <CategoryForm
        item={item}
        onDone={() => { setEditing(false); onChanged(); }}
        onCancel={() => setEditing(false)}
      />
    );
  }

  return (
    <div className="flex items-center justify-between rounded-lg border border-border bg-surface p-4">
      <div>
        <p className="text-sm font-semibold text-ink">{item.name.hy ?? item.name.ru}</p>
        <p className="mt-0.5 font-mono text-xs text-ink-muted">
          hy:{item.slugHy} · ru:{item.slugRu} · en:{item.slugEn}
        </p>
        {item.productCount !== undefined ? (
          <p className="mt-0.5 text-xs text-ink-faint">{item.productCount} ապրանք</p>
        ) : null}
      </div>
      <button type="button" onClick={() => setEditing(true)} className="text-xs font-medium text-primary hover:underline">
        Խմբագրել
      </button>
    </div>
  );
}

function CategoryForm({
  item,
  onDone,
  onCancel,
}: {
  item?: AdminProductCategoryDto;
  onDone: () => void;
  onCancel: () => void;
}) {
  const [form, setForm] = useState<FormState>(() => toFormState(item));
  const [status, setStatus] = useState<"idle" | "saving" | "error">("idle");

  function set<K extends keyof FormState>(key: K, value: FormState[K]) {
    setForm((f) => ({ ...f, [key]: value }));
  }

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault();
    setStatus("saving");

    try {
      const response = await fetch(item ? `/api/admin/product-categories/${item.id}` : "/api/admin/product-categories", {
        method: item ? "PATCH" : "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(buildPayload(form)),
      });

      if (!response.ok) {
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

      <TranslatedFields label="Անվանում" value={form.name} onChange={(v) => set("name", v)} />
      <TranslatedFields label="Կարճ նկարագրություն (կատեգորիայի էջի ինտրո)" value={form.shortDescription} onChange={(v) => set("shortDescription", v)} multiline />

      <div className="grid gap-2 sm:grid-cols-3">
        <div className="space-y-1.5">
          <p className={labelClasses}>Icon (lucide key)</p>
          <input value={form.icon} onChange={(e) => set("icon", e.target.value)} placeholder="sofa, droplet, wind..." className={fieldClasses} />
        </div>
        <div className="space-y-1.5">
          <p className={labelClasses}>Կարգ (sort)</p>
          <input type="number" value={form.sortOrder} onChange={(e) => set("sortOrder", e.target.value)} className={fieldClasses} />
        </div>
      </div>

      <div className="flex items-center gap-3">
        <button type="submit" disabled={status === "saving"} className="rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:bg-primary-hover disabled:opacity-50">
          Պահպանել
        </button>
        <button type="button" onClick={onCancel} className="text-sm text-ink-muted hover:text-ink">
          Չեղարկել
        </button>
        {status === "error" ? <span className="text-sm text-danger">Չհաջողվեց պահպանել։</span> : null}
      </div>
    </form>
  );
}
