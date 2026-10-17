"use client";

import { useState } from "react";
import { useRouter } from "next/navigation";
import type { AdminServiceCategoryDto, AdminServiceDto, AdminTranslated, AdminTranslatedList, AdminFaqList } from "@/lib/adminApi";
import type { CarBodyType, ContaminationType, InteriorMaterial, ServicePriceType, ServiceStatus } from "@/lib/api";
import {
  TranslatedFields,
  TranslatedListFields,
  FaqFields,
  emptyTranslated,
  emptyTranslatedList,
  toTranslated,
  fieldClasses,
  labelClasses,
} from "./shared";
import { ImageUploadField } from "./ImageUploadField";
import { Badge, buttonClasses } from "../_components/ui";

const STATUS_LABELS: Record<ServiceStatus, string> = {
  draft: "Սևագիր (չհրապարակված)",
  coming_soon: "Շուտով",
  active: "Ակտիվ (պատվիրելի)",
  temporarily_unavailable: "Ժամանակավոր անհասանելի",
  archived: "Արխիվացված",
};
const STATUSES = Object.keys(STATUS_LABELS) as ServiceStatus[];

const CAR_TYPES: CarBodyType[] = ["compact", "sedan", "wagon", "crossover", "suv", "minivan", "minibus", "commercial"];
const MATERIALS: InteriorMaterial[] = ["fabric", "leather", "alcantara", "mixed", "plastic"];
const CONTAMINATION_TYPES: ContaminationType[] = [
  "dust", "food_drinks", "coffee", "grease_oil", "blood", "urine", "vomit", "ink", "pet_hair", "tobacco_odor", "dampness", "unknown",
];
const PRICE_TYPE_LABELS: Record<ServicePriceType, string> = {
  fixed: "Ֆիքս գին",
  from: "-ից գին",
  range: "Միջակայք",
  on_inspection: "Զննումից հետո",
  by_car_type: "Ըստ մեքենայի տեսակի",
};
const PRICE_TYPES = Object.keys(PRICE_TYPE_LABELS) as ServicePriceType[];

type FormState = {
  categoryId: string;
  slug: string;
  icon: string;
  image: string;
  sortOrder: string;
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
  warnings: AdminTranslatedList;
  dryingTimeNote: AdminTranslated;
  faq: AdminFaqList;
  carTypes: CarBodyType[];
  materials: InteriorMaterial[];
  contaminationTypes: ContaminationType[];
  priceType: ServicePriceType | "";
  priceAmount: string;
  priceMin: string;
  priceMax: string;
  durationMinutes: string;
  seoTitle: AdminTranslated;
  seoDescription: AdminTranslated;
};

function toFormState(item: AdminServiceDto): FormState {
  return {
    categoryId: String(item.categoryId),
    slug: item.slug,
    icon: item.icon ?? "",
    image: item.image ?? "",
    sortOrder: String(item.sortOrder),
    status: item.status,
    name: toTranslated(item.name),
    shortDescription: toTranslated(item.shortDescription),
    description: toTranslated(item.description),
    whoItsFor: toTranslated(item.whoItsFor),
    includedItems: item.includedItems ?? emptyTranslatedList(),
    excludedItems: item.excludedItems ?? emptyTranslatedList(),
    processSteps: item.processSteps ?? emptyTranslatedList(),
    equipment: toTranslated(item.equipment),
    durationNote: toTranslated(item.durationNote),
    priceFactors: item.priceFactors ?? emptyTranslatedList(),
    warnings: item.warnings ?? emptyTranslatedList(),
    dryingTimeNote: toTranslated(item.dryingTimeNote),
    faq: item.faq ?? {},
    carTypes: item.carTypes ?? [],
    materials: item.materials ?? [],
    contaminationTypes: item.contaminationTypes ?? [],
    priceType: item.priceType ?? "",
    priceAmount: item.priceAmount != null ? String(item.priceAmount) : "",
    priceMin: item.priceMin != null ? String(item.priceMin) : "",
    priceMax: item.priceMax != null ? String(item.priceMax) : "",
    durationMinutes: item.durationMinutes != null ? String(item.durationMinutes) : "",
    seoTitle: toTranslated(item.seoTitle),
    seoDescription: toTranslated(item.seoDescription),
  };
}

function emptyForm(defaultCategoryId: number | ""): FormState {
  return {
    categoryId: defaultCategoryId ? String(defaultCategoryId) : "",
    slug: "",
    icon: "",
    image: "",
    sortOrder: "0",
    status: "draft",
    name: emptyTranslated(),
    shortDescription: emptyTranslated(),
    description: emptyTranslated(),
    whoItsFor: emptyTranslated(),
    includedItems: emptyTranslatedList(),
    excludedItems: emptyTranslatedList(),
    processSteps: emptyTranslatedList(),
    equipment: emptyTranslated(),
    durationNote: emptyTranslated(),
    priceFactors: emptyTranslatedList(),
    warnings: emptyTranslatedList(),
    dryingTimeNote: emptyTranslated(),
    faq: {},
    carTypes: [],
    materials: [],
    contaminationTypes: [],
    priceType: "",
    priceAmount: "",
    priceMin: "",
    priceMax: "",
    durationMinutes: "",
    seoTitle: emptyTranslated(),
    seoDescription: emptyTranslated(),
  };
}

function payloadFrom(form: FormState) {
  return {
    category_id: Number(form.categoryId) || undefined,
    slug: form.slug.trim(),
    icon: form.icon.trim() || null,
    image: form.image.trim() || null,
    sort_order: Number(form.sortOrder) || 0,
    status: form.status,
    name: form.name,
    short_description: form.shortDescription,
    description: form.description,
    who_its_for: form.whoItsFor,
    included_items: form.includedItems,
    excluded_items: form.excludedItems,
    process_steps: form.processSteps,
    equipment: form.equipment,
    duration_note: form.durationNote,
    price_factors: form.priceFactors,
    warnings: form.warnings,
    drying_time_note: form.dryingTimeNote,
    faq: form.faq,
    car_types: form.carTypes,
    materials: form.materials,
    contamination_types: form.contaminationTypes,
    price_type: form.priceType || null,
    price_amount: form.priceAmount ? Number(form.priceAmount) : null,
    price_min: form.priceMin ? Number(form.priceMin) : null,
    price_max: form.priceMax ? Number(form.priceMax) : null,
    duration_minutes: form.durationMinutes ? Number(form.durationMinutes) : null,
    seo_title: form.seoTitle,
    seo_description: form.seoDescription,
  };
}

function MultiCheckbox<T extends string>({
  label,
  options,
  optionLabels,
  selected,
  onChange,
}: {
  label: string;
  options: T[];
  optionLabels: Record<T, string>;
  selected: T[];
  onChange: (next: T[]) => void;
}) {
  return (
    <div className="space-y-1.5">
      <p className={labelClasses}>{label}</p>
      <div className="flex flex-wrap gap-x-4 gap-y-1.5">
        {options.map((option) => (
          <label key={option} className="flex items-center gap-1.5 text-sm text-ink-muted">
            <input
              type="checkbox"
              checked={selected.includes(option)}
              onChange={() =>
                onChange(selected.includes(option) ? selected.filter((v) => v !== option) : [...selected, option])
              }
              className="size-3.5 rounded border-border-strong accent-primary"
            />
            {optionLabels[option]}
          </label>
        ))}
      </div>
    </div>
  );
}

const carTypeLabels: Record<CarBodyType, string> = {
  compact: "Կոմպակտ", sedan: "Սեդան", wagon: "Ունիվերսալ", crossover: "Կրոսովեր",
  suv: "Ամբարանման", minivan: "Մինիվեն", minibus: "Միկրոավտոբուս", commercial: "Կոմերցիոն",
};
const materialLabels: Record<InteriorMaterial, string> = {
  fabric: "Տեքստիլ", leather: "Կաշի", alcantara: "Ալկանտարա", mixed: "Խառը", plastic: "Պլաստիկ",
};
const contaminationLabels: Record<ContaminationType, string> = {
  dust: "Փոշի", food_drinks: "Սնունդ/ըմպելիք", coffee: "Սուրճ", grease_oil: "Ճարպ/յուղ",
  blood: "Արյուն", urine: "Մեզ", vomit: "Փսխում", ink: "Թանաք", pet_hair: "Կենդանու մազ",
  tobacco_odor: "Ծխախոտի հոտ", dampness: "Խոնավություն", unknown: "Անհայտ",
};

function ServiceFields({ form, setForm, disabled }: { form: FormState; setForm: (f: FormState) => void; disabled?: boolean }) {
  function set<K extends keyof FormState>(key: K, value: FormState[K]) {
    setForm({ ...form, [key]: value });
  }

  return (
    <>
      <TranslatedFields label="Անվանում" value={form.name} onChange={(v) => set("name", v)} />
      <TranslatedFields label="Կարճ նկարագրություն" value={form.shortDescription} onChange={(v) => set("shortDescription", v)} multiline />
      <TranslatedFields label="Ամբողջական նկարագրություն" value={form.description} onChange={(v) => set("description", v)} multiline />
      <TranslatedFields label="Ում համար է" value={form.whoItsFor} onChange={(v) => set("whoItsFor", v)} multiline />
      <TranslatedListFields label="Ինչ է ներառված" hint="մեկը մեկ տողում" value={form.includedItems} onChange={(v) => set("includedItems", v)} />
      <TranslatedListFields label="Ինչ չի ներառված" hint="մեկը մեկ տողում" value={form.excludedItems} onChange={(v) => set("excludedItems", v)} />
      <TranslatedListFields label="Աշխատանքի փուլեր" hint="մեկը մեկ տողում" value={form.processSteps} onChange={(v) => set("processSteps", v)} />
      <TranslatedFields label="Սարքավորում/միջոցներ" value={form.equipment} onChange={(v) => set("equipment", v)} multiline />
      <TranslatedFields label="Տևողության նշում" value={form.durationNote} onChange={(v) => set("durationNote", v)} multiline />
      <TranslatedFields label="Չորացման ժամանակի նշում" value={form.dryingTimeNote} onChange={(v) => set("dryingTimeNote", v)} multiline />
      <TranslatedListFields label="Գնի գործոններ" hint="մեկը մեկ տողում" value={form.priceFactors} onChange={(v) => set("priceFactors", v)} />
      <TranslatedListFields label="Սահմանափակումներ/նշումներ" hint="մեկը մեկ տողում" value={form.warnings} onChange={(v) => set("warnings", v)} />
      <FaqFields value={form.faq} onChange={(v) => set("faq", v)} />

      <MultiCheckbox label="Մեքենայի տեսակներ" options={CAR_TYPES} optionLabels={carTypeLabels} selected={form.carTypes} onChange={(v) => set("carTypes", v)} />
      <MultiCheckbox label="Նյութեր" options={MATERIALS} optionLabels={materialLabels} selected={form.materials} onChange={(v) => set("materials", v)} />
      <MultiCheckbox label="Աղտոտման տեսակներ" options={CONTAMINATION_TYPES} optionLabels={contaminationLabels} selected={form.contaminationTypes} onChange={(v) => set("contaminationTypes", v)} />

      <div className="grid gap-2 sm:grid-cols-5">
        <div className="space-y-1.5">
          <p className={labelClasses}>Գնի տեսակ</p>
          <select value={form.priceType} disabled={disabled} onChange={(e) => set("priceType", e.target.value as ServicePriceType)} className={fieldClasses}>
            <option value=""></option>
            {PRICE_TYPES.map((p) => (
              <option key={p} value={p}>{PRICE_TYPE_LABELS[p]}</option>
            ))}
          </select>
        </div>
        <div className="space-y-1.5">
          <p className={labelClasses}>Գին (֏)</p>
          <input type="number" value={form.priceAmount} disabled={disabled} onChange={(e) => set("priceAmount", e.target.value)} className={fieldClasses} />
        </div>
        <div className="space-y-1.5">
          <p className={labelClasses}>Նվազագույն (֏)</p>
          <input type="number" value={form.priceMin} disabled={disabled} onChange={(e) => set("priceMin", e.target.value)} className={fieldClasses} />
        </div>
        <div className="space-y-1.5">
          <p className={labelClasses}>Առավելագույն (֏)</p>
          <input type="number" value={form.priceMax} disabled={disabled} onChange={(e) => set("priceMax", e.target.value)} className={fieldClasses} />
        </div>
        <div className="space-y-1.5">
          <p className={labelClasses}>Տևողություն (րոպե)</p>
          <input type="number" value={form.durationMinutes} disabled={disabled} onChange={(e) => set("durationMinutes", e.target.value)} className={fieldClasses} />
        </div>
      </div>

      <TranslatedFields label="SEO վերնագիր" value={form.seoTitle} onChange={(v) => set("seoTitle", v)} />
      <TranslatedFields label="SEO նկարագրություն" value={form.seoDescription} onChange={(v) => set("seoDescription", v)} multiline />
    </>
  );
}

function ServiceCard({
  item,
  categories,
  onChanged,
}: {
  item: AdminServiceDto;
  categories: AdminServiceCategoryDto[];
  onChanged: () => void;
}) {
  const [form, setForm] = useState<FormState>(() => toFormState(item));
  const [expanded, setExpanded] = useState(false);
  const [pending, setPending] = useState(false);
  const [error, setError] = useState(false);

  async function save() {
    setPending(true);
    setError(false);
    try {
      const response = await fetch(`/api/admin/services/${item.id}`, {
        method: "PATCH",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(payloadFrom(form)),
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
    try {
      const response = await fetch(`/api/admin/services/${item.id}`, { method: "DELETE" });
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
    <div className="space-y-3 rounded-lg border border-border bg-surface p-4">
      <div className="flex flex-wrap items-center gap-3">
        <Badge>{item.slug}</Badge>
        <select
          value={form.categoryId}
          disabled={pending}
          onChange={(e) => setForm({ ...form, categoryId: e.target.value })}
          className={`${fieldClasses} w-auto`}
        >
          {categories.map((c) => (
            <option key={c.id} value={c.id}>{c.name.hy ?? c.slug}</option>
          ))}
        </select>
        <select
          value={form.status}
          disabled={pending}
          onChange={(e) => setForm({ ...form, status: e.target.value as ServiceStatus })}
          className={`${fieldClasses} w-auto`}
        >
          {STATUSES.map((s) => (
            <option key={s} value={s}>{STATUS_LABELS[s]}</option>
          ))}
        </select>
        <button type="button" onClick={() => setExpanded((v) => !v)} className="ml-auto text-xs font-medium text-primary hover:underline">
          {expanded ? "Փակել" : "Խմբագրել"}
        </button>
      </div>

      <p className="text-sm font-semibold text-ink">{form.name.hy || item.slug}</p>

      {expanded ? (
        <>
          <div className="grid gap-2 sm:grid-cols-3">
            <div className="space-y-1.5">
              <p className={labelClasses}>Slug</p>
              <input value={form.slug} disabled={pending} onChange={(e) => setForm({ ...form, slug: e.target.value })} className={fieldClasses} />
            </div>
            <div className="space-y-1.5">
              <p className={labelClasses}>Icon (lucide slug)</p>
              <input value={form.icon} disabled={pending} onChange={(e) => setForm({ ...form, icon: e.target.value })} className={fieldClasses} />
            </div>
            <div className="space-y-1.5">
              <p className={labelClasses}>Կարգ</p>
              <input type="number" value={form.sortOrder} disabled={pending} onChange={(e) => setForm({ ...form, sortOrder: e.target.value })} className={fieldClasses} />
            </div>
          </div>
          <ImageUploadField label="Հիմնական նկար" value={form.image} onChange={(v) => setForm({ ...form, image: v })} folder="services" disabled={pending} />
          <ServiceFields form={form} setForm={setForm} disabled={pending} />
        </>
      ) : null}

      <div className="flex items-center gap-3">
        <button type="button" onClick={save} disabled={pending} className={buttonClasses("primary", "sm")}>
          Պահպանել
        </button>
        <button type="button" onClick={remove} disabled={pending} className={buttonClasses("secondary", "sm")}>
          Ջնջել
        </button>
        {error ? <span className="text-xs text-danger">Չհաջողվեց կատարել գործողությունը։</span> : null}
      </div>
    </div>
  );
}

function NewServiceForm({
  categories,
  onCreated,
  onCancel,
}: {
  categories: AdminServiceCategoryDto[];
  onCreated: () => void;
  onCancel: () => void;
}) {
  const autoCareCategory = categories.find((c) => c.slug === "auto-care");
  const [form, setForm] = useState<FormState>(() => emptyForm(autoCareCategory?.id ?? categories[0]?.id ?? ""));
  const [status, setStatus] = useState<"idle" | "saving" | "error">("idle");

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault();
    setStatus("saving");
    try {
      const response = await fetch("/api/admin/services", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(payloadFrom(form)),
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
      <div className="grid gap-2 sm:grid-cols-4">
        <div className="space-y-1.5">
          <p className={labelClasses}>Կատեգորիա</p>
          <select value={form.categoryId} onChange={(e) => setForm({ ...form, categoryId: e.target.value })} className={fieldClasses}>
            {categories.map((c) => (
              <option key={c.id} value={c.id}>{c.name.hy ?? c.slug}</option>
            ))}
          </select>
        </div>
        <div className="space-y-1.5">
          <p className={labelClasses}>Slug</p>
          <input value={form.slug} onChange={(e) => setForm({ ...form, slug: e.target.value })} placeholder="new-auto-care-service" required className={fieldClasses} />
        </div>
        <div className="space-y-1.5">
          <p className={labelClasses}>Icon</p>
          <input value={form.icon} onChange={(e) => setForm({ ...form, icon: e.target.value })} placeholder="car" className={fieldClasses} />
        </div>
        <div className="space-y-1.5">
          <p className={labelClasses}>Կարգավիճակ</p>
          <select value={form.status} onChange={(e) => setForm({ ...form, status: e.target.value as ServiceStatus })} className={fieldClasses}>
            {STATUSES.map((s) => (
              <option key={s} value={s}>{STATUS_LABELS[s]}</option>
            ))}
          </select>
        </div>
      </div>

      <ImageUploadField label="Հիմնական նկար" value={form.image} onChange={(v) => setForm({ ...form, image: v })} folder="services" />
      <ServiceFields form={form} setForm={setForm} />

      <div className="flex items-center gap-3">
        <button type="submit" disabled={status === "saving"} className={buttonClasses("primary", "md")}>
          Ավելացնել
        </button>
        <button type="button" onClick={onCancel} className="text-sm text-ink-muted hover:text-ink">
          Չեղարկել
        </button>
        {status === "error" ? <span className="text-sm text-danger">Չհաջողվեց ավելացնել։</span> : null}
      </div>
    </form>
  );
}

export function ServicesManager({ items, categories }: { items: AdminServiceDto[]; categories: AdminServiceCategoryDto[] }) {
  const router = useRouter();
  const sorted = [...items].sort((a, b) => a.sortOrder - b.sortOrder);
  const [creating, setCreating] = useState(false);

  return (
    <div className="space-y-4">
      {sorted.map((item) => (
        <ServiceCard key={item.id} item={item} categories={categories} onChanged={() => router.refresh()} />
      ))}
      {sorted.length === 0 ? (
        <p className="rounded-lg border border-dashed border-border-strong bg-surface px-4 py-6 text-center text-sm text-ink-muted">Ծառայություններ դեռ չկան։</p>
      ) : null}

      {creating ? (
        <NewServiceForm categories={categories} onCreated={() => { setCreating(false); router.refresh(); }} onCancel={() => setCreating(false)} />
      ) : (
        <button type="button" onClick={() => setCreating(true)} className={buttonClasses("secondary", "md")}>
          + Ավելացնել ծառայություն
        </button>
      )}
    </div>
  );
}
