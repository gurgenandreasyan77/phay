"use client";

import { useState } from "react";
import { useRouter } from "next/navigation";
import type { AdditionalServiceCategory, PriceUnit } from "@/lib/api";
import type { AdminAdditionalServiceDto, AdminTranslated } from "@/lib/adminApi";
import { ImageUploadField } from "./ImageUploadField";
import { Badge, Button, EmptyCard, FieldLabel, Input, Select } from "../_components/ui";

type Locale = "hy" | "ru" | "en";
const LOCALES: { key: Locale; label: string }[] = [
  { key: "hy", label: "Հայերեն" },
  { key: "ru", label: "Ռուսերեն" },
  { key: "en", label: "Անգլերեն" },
];

const CATEGORY_LABELS: Record<AdditionalServiceCategory, string> = {
  upholstery: "Փափուկ կահույք",
  textile: "Տեքստիլ",
  windows: "Պատուհաններ",
};
const CATEGORIES = Object.keys(CATEGORY_LABELS) as AdditionalServiceCategory[];

const UNIT_LABELS: Record<PriceUnit, string> = {
  flat: "Ֆիքս գին",
  sqm: "Գին/մ²",
};
const UNITS = Object.keys(UNIT_LABELS) as PriceUnit[];

function emptyTranslated(): AdminTranslated {
  return { hy: "", ru: "", en: "" };
}

type FormState = {
  slug: string;
  category: AdditionalServiceCategory;
  name: AdminTranslated;
  note: AdminTranslated;
  alt: AdminTranslated;
  image: string;
  priceAmount: string;
  priceUnit: PriceUnit;
  discountPercent: string;
  serviceSlug: string;
  sortOrder: string;
  isActive: boolean;
};

function toFormState(item: AdminAdditionalServiceDto): FormState {
  return {
    slug: item.slug,
    category: item.category,
    name: { hy: item.name.hy ?? "", ru: item.name.ru ?? "", en: item.name.en ?? "" },
    note: { hy: item.note.hy ?? "", ru: item.note.ru ?? "", en: item.note.en ?? "" },
    alt: { hy: item.alt.hy ?? "", ru: item.alt.ru ?? "", en: item.alt.en ?? "" },
    image: item.image,
    priceAmount: String(item.priceAmount),
    priceUnit: item.priceUnit,
    discountPercent: String(item.discountPercent),
    serviceSlug: item.serviceSlug ?? "",
    sortOrder: String(item.sortOrder),
    isActive: item.isActive,
  };
}

function TranslatedFields({
  label,
  value,
  onChange,
}: {
  label: string;
  value: AdminTranslated;
  onChange: (next: AdminTranslated) => void;
}) {
  return (
    <div className="space-y-1.5">
      <FieldLabel>{label}</FieldLabel>
      <div className="grid gap-2 sm:grid-cols-3">
        {LOCALES.map(({ key, label: localeLabel }) => (
          <Input
            key={key}
            value={value[key] ?? ""}
            placeholder={localeLabel}
            onChange={(e) => onChange({ ...value, [key]: e.target.value })}
          />
        ))}
      </div>
    </div>
  );
}

export function AdditionalServicesManager({ items }: { items: AdminAdditionalServiceDto[] }) {
  const router = useRouter();
  const sorted = [...items].sort((a, b) => a.sortOrder - b.sortOrder);
  const [creating, setCreating] = useState(false);

  return (
    <div className="space-y-4">
      {sorted.map((item) => (
        <AdditionalServiceCard key={item.id} item={item} onChanged={() => router.refresh()} />
      ))}
      {sorted.length === 0 ? <EmptyCard>Լրացուցիչ ծառայություններ դեռ չկան։</EmptyCard> : null}

      {creating ? (
        <NewAdditionalServiceForm
          onCreated={() => {
            setCreating(false);
            router.refresh();
          }}
          onCancel={() => setCreating(false)}
        />
      ) : (
        <Button variant="secondary" onClick={() => setCreating(true)}>
          + Ավելացնել ծառայություն
        </Button>
      )}
    </div>
  );
}

function AdditionalServiceCard({
  item,
  onChanged,
}: {
  item: AdminAdditionalServiceDto;
  onChanged: () => void;
}) {
  const [form, setForm] = useState<FormState>(() => toFormState(item));
  const [pending, setPending] = useState(false);
  const [error, setError] = useState(false);

  function set<K extends keyof FormState>(key: K, value: FormState[K]) {
    setForm((f) => ({ ...f, [key]: value }));
  }

  async function save() {
    setPending(true);
    setError(false);
    try {
      const response = await fetch(`/api/admin/additional-services/${item.id}`, {
        method: "PATCH",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          category: form.category,
          name: form.name,
          note: form.note,
          alt: form.alt,
          image: form.image,
          price_amount: Number(form.priceAmount) || 0,
          price_unit: form.priceUnit,
          discount_percent: Number(form.discountPercent) || 0,
          service_slug: form.serviceSlug || null,
          sort_order: Number(form.sortOrder) || 0,
          is_active: form.isActive,
        }),
      });

      if (!response.ok) {
        setError(true);
        return;
      }

      onChanged();
    } finally {
      setPending(false);
    }
  }

  async function remove() {
    if (!confirm("Ջնջե՞լ այս ծառայությունը։")) return;
    setPending(true);
    setError(false);
    try {
      const response = await fetch(`/api/admin/additional-services/${item.id}`, { method: "DELETE" });
      if (!response.ok) {
        setError(true);
        return;
      }
      onChanged();
    } finally {
      setPending(false);
    }
  }

  return (
    <div className={`space-y-3 rounded-lg border bg-surface p-4 ${form.isActive ? "border-border" : "border-border opacity-60"}`}>
      <div className="flex flex-wrap items-center gap-3">
        <Badge>{item.slug}</Badge>
        <Select
          value={form.category}
          disabled={pending}
          onChange={(e) => set("category", e.target.value as AdditionalServiceCategory)}
          className="w-auto"
        >
          {CATEGORIES.map((c) => (
            <option key={c} value={c}>
              {CATEGORY_LABELS[c]}
            </option>
          ))}
        </Select>

        <label className="ml-auto flex items-center gap-2 text-sm text-ink">
          <input
            type="checkbox"
            checked={form.isActive}
            disabled={pending}
            onChange={(e) => set("isActive", e.target.checked)}
            className="h-4 w-4 accent-primary"
          />
          Ակտիվ
        </label>
      </div>

      <TranslatedFields label="Անվանում" value={form.name} onChange={(v) => set("name", v)} />
      <TranslatedFields label="Alt տեքստ (նկար)" value={form.alt} onChange={(v) => set("alt", v)} />
      <TranslatedFields label="Նշում (ոչ պարտադիր)" value={form.note} onChange={(v) => set("note", v)} />

      <div className="grid gap-2 sm:grid-cols-6">
        <ImageUploadField
          label="Նկարի ուղի"
          value={form.image}
          onChange={(path) => set("image", path)}
          folder="additional-services"
          disabled={pending}
        />
        <div className="space-y-1.5">
          <FieldLabel>Գին (֏)</FieldLabel>
          <Input
            type="number"
            value={form.priceAmount}
            disabled={pending}
            onChange={(e) => set("priceAmount", e.target.value)}
          />
        </div>
        <div className="space-y-1.5">
          <FieldLabel>Միավոր</FieldLabel>
          <Select value={form.priceUnit} disabled={pending} onChange={(e) => set("priceUnit", e.target.value as PriceUnit)}>
            {UNITS.map((u) => (
              <option key={u} value={u}>
                {UNIT_LABELS[u]}
              </option>
            ))}
          </Select>
        </div>
        <div className="space-y-1.5">
          <FieldLabel>Զեղչ (%)</FieldLabel>
          <Input
            type="number"
            min={0}
            max={100}
            value={form.discountPercent}
            disabled={pending}
            onChange={(e) => set("discountPercent", e.target.value)}
          />
          {Number(form.discountPercent) > 0 ? (
            <p className="text-xs text-success">
              {Number(form.priceAmount) || 0} ֏ →{" "}
              <span className="font-semibold">
                {Math.round((Number(form.priceAmount) || 0) * (1 - Number(form.discountPercent) / 100))} ֏
              </span>
            </p>
          ) : null}
        </div>
        <div className="space-y-1.5">
          <FieldLabel>Ծառայության slug (ոչ պարտադիր)</FieldLabel>
          <Input value={form.serviceSlug} disabled={pending} onChange={(e) => set("serviceSlug", e.target.value)} />
        </div>
        <div className="space-y-1.5">
          <FieldLabel>Կարգ</FieldLabel>
          <Input type="number" value={form.sortOrder} disabled={pending} onChange={(e) => set("sortOrder", e.target.value)} />
        </div>
      </div>

      <div className="flex items-center gap-3">
        <Button size="sm" onClick={save} disabled={pending}>
          Պահպանել
        </Button>
        <Button size="sm" variant="secondary" onClick={remove} disabled={pending}>
          Ջնջել
        </Button>
        {error ? <span className="text-xs text-danger">Չհաջողվեց կատարել գործողությունը։</span> : null}
      </div>
    </div>
  );
}

function NewAdditionalServiceForm({ onCreated, onCancel }: { onCreated: () => void; onCancel: () => void }) {
  const [form, setForm] = useState<FormState>({
    slug: "",
    category: "upholstery",
    name: emptyTranslated(),
    note: emptyTranslated(),
    alt: emptyTranslated(),
    image: "",
    priceAmount: "",
    priceUnit: "flat",
    discountPercent: "0",
    serviceSlug: "",
    sortOrder: "0",
    isActive: true,
  });
  const [status, setStatus] = useState<"idle" | "saving" | "error">("idle");

  function set<K extends keyof FormState>(key: K, value: FormState[K]) {
    setForm((f) => ({ ...f, [key]: value }));
  }

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault();
    setStatus("saving");

    try {
      const response = await fetch("/api/admin/additional-services", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          slug: form.slug.trim(),
          category: form.category,
          name: form.name,
          note: form.note.hy || form.note.ru || form.note.en ? form.note : undefined,
          alt: form.alt,
          image: form.image.trim(),
          price_amount: Number(form.priceAmount) || 0,
          price_unit: form.priceUnit,
          discount_percent: Number(form.discountPercent) || 0,
          service_slug: form.serviceSlug.trim() || undefined,
          sort_order: Number(form.sortOrder) || 0,
          is_active: form.isActive,
        }),
      });

      if (!response.ok) {
        setStatus("error");
        return;
      }

      onCreated();
    } catch {
      setStatus("error");
    }
  }

  return (
    <form onSubmit={handleSubmit} className="space-y-3 rounded-lg border border-primary-light bg-primary-light/30 p-4">
      <div className="grid gap-2 sm:grid-cols-3">
        <div className="space-y-1.5">
          <FieldLabel>Slug</FieldLabel>
          <Input
            value={form.slug}
            onChange={(e) => set("slug", e.target.value)}
            placeholder="two-seat-sofa"
            required
          />
        </div>
        <div className="space-y-1.5">
          <FieldLabel>Կատեգորիա</FieldLabel>
          <Select value={form.category} onChange={(e) => set("category", e.target.value as AdditionalServiceCategory)}>
            {CATEGORIES.map((c) => (
              <option key={c} value={c}>
                {CATEGORY_LABELS[c]}
              </option>
            ))}
          </Select>
        </div>
        <ImageUploadField
          label="Նկարի ուղի"
          value={form.image}
          onChange={(path) => set("image", path)}
          folder="additional-services"
        />
      </div>

      <TranslatedFields label="Անվանում" value={form.name} onChange={(v) => set("name", v)} />
      <TranslatedFields label="Alt տեքստ (նկար)" value={form.alt} onChange={(v) => set("alt", v)} />
      <TranslatedFields label="Նշում (ոչ պարտադիր)" value={form.note} onChange={(v) => set("note", v)} />

      <div className="grid gap-2 sm:grid-cols-5">
        <div className="space-y-1.5">
          <FieldLabel>Գին (֏)</FieldLabel>
          <Input type="number" value={form.priceAmount} onChange={(e) => set("priceAmount", e.target.value)} required />
        </div>
        <div className="space-y-1.5">
          <FieldLabel>Միավոր</FieldLabel>
          <Select value={form.priceUnit} onChange={(e) => set("priceUnit", e.target.value as PriceUnit)}>
            {UNITS.map((u) => (
              <option key={u} value={u}>
                {UNIT_LABELS[u]}
              </option>
            ))}
          </Select>
        </div>
        <div className="space-y-1.5">
          <FieldLabel>Զեղչ (%)</FieldLabel>
          <Input
            type="number"
            min={0}
            max={100}
            value={form.discountPercent}
            onChange={(e) => set("discountPercent", e.target.value)}
          />
        </div>
        <div className="space-y-1.5">
          <FieldLabel>Ծառայության slug (ոչ պարտադիր)</FieldLabel>
          <Input value={form.serviceSlug} onChange={(e) => set("serviceSlug", e.target.value)} />
        </div>
        <div className="space-y-1.5">
          <FieldLabel>Կարգ</FieldLabel>
          <Input type="number" value={form.sortOrder} onChange={(e) => set("sortOrder", e.target.value)} />
        </div>
      </div>

      <div className="flex items-center gap-3">
        <Button type="submit" disabled={status === "saving"}>
          Ավելացնել
        </Button>
        <Button type="button" variant="ghost" onClick={onCancel}>
          Չեղարկել
        </Button>
        {status === "error" ? <span className="text-sm text-danger">Չհաջողվեց ավելացնել։</span> : null}
      </div>
    </form>
  );
}
