"use client";

import { useState } from "react";
import { useRouter } from "next/navigation";
import type { AdminServiceDto, AdminServiceGalleryItemDto, AdminTranslated } from "@/lib/adminApi";
import type { CarBodyType } from "@/lib/api";
import { TranslatedFields, emptyTranslated, toTranslated, fieldClasses, labelClasses } from "../services/shared";
import { ImageUploadField } from "../services/ImageUploadField";
import { buttonClasses } from "@/ui";

const CAR_TYPES: CarBodyType[] = ["compact", "sedan", "wagon", "crossover", "suv", "minivan", "minibus", "commercial"];

type FormState = {
  serviceId: string;
  carType: string;
  label: AdminTranslated;
  description: AdminTranslated;
  beforeImage: string;
  afterImage: string;
  altBefore: AdminTranslated;
  altAfter: AdminTranslated;
  performedAt: string;
  clientConsent: boolean;
  isPublished: boolean;
  sortOrder: string;
};

function toFormState(item: AdminServiceGalleryItemDto): FormState {
  return {
    serviceId: item.serviceId != null ? String(item.serviceId) : "",
    carType: item.carType ?? "",
    label: toTranslated(item.label),
    description: toTranslated(item.description),
    beforeImage: item.beforeImage,
    afterImage: item.afterImage,
    altBefore: toTranslated(item.altBefore),
    altAfter: toTranslated(item.altAfter),
    performedAt: item.performedAt ?? "",
    clientConsent: item.clientConsent,
    isPublished: item.isPublished,
    sortOrder: String(item.sortOrder),
  };
}

function emptyForm(): FormState {
  return {
    serviceId: "",
    carType: "",
    label: emptyTranslated(),
    description: emptyTranslated(),
    beforeImage: "",
    afterImage: "",
    altBefore: emptyTranslated(),
    altAfter: emptyTranslated(),
    performedAt: "",
    clientConsent: false,
    isPublished: false,
    sortOrder: "0",
  };
}

function payloadFrom(form: FormState) {
  return {
    service_id: form.serviceId ? Number(form.serviceId) : null,
    car_type: form.carType || null,
    label: form.label,
    description: form.description,
    before_image: form.beforeImage.trim(),
    after_image: form.afterImage.trim(),
    alt_before: form.altBefore,
    alt_after: form.altAfter,
    performed_at: form.performedAt || null,
    client_consent: form.clientConsent,
    is_published: form.isPublished,
    sort_order: Number(form.sortOrder) || 0,
  };
}

function GalleryFields({ form, setForm, services, disabled }: {
  form: FormState;
  setForm: (f: FormState) => void;
  services: AdminServiceDto[];
  disabled?: boolean;
}) {
  return (
    <>
      <div className="grid gap-2 sm:grid-cols-4">
        <select value={form.serviceId} disabled={disabled} onChange={(e) => setForm({ ...form, serviceId: e.target.value })} className={fieldClasses}>
          <option value="">— ընդհանուր (ոչ մեկին կապված) —</option>
          {services.map((s) => (
            <option key={s.id} value={s.id}>{s.name.hy ?? s.slug}</option>
          ))}
        </select>
        <select value={form.carType} disabled={disabled} onChange={(e) => setForm({ ...form, carType: e.target.value })} className={fieldClasses}>
          <option value=""></option>
          {CAR_TYPES.map((t) => (
            <option key={t} value={t}>{t}</option>
          ))}
        </select>
        <input type="date" value={form.performedAt} disabled={disabled} onChange={(e) => setForm({ ...form, performedAt: e.target.value })} className={fieldClasses} />
        <input type="number" value={form.sortOrder} disabled={disabled} onChange={(e) => setForm({ ...form, sortOrder: e.target.value })} placeholder="Կարգ" className={fieldClasses} />
      </div>
      <TranslatedFields label="Պիտակ" value={form.label} onChange={(v) => setForm({ ...form, label: v })} />
      <TranslatedFields label="Նկարագրություն" value={form.description} onChange={(v) => setForm({ ...form, description: v })} multiline />
      <div className="grid gap-4 sm:grid-cols-2">
        <ImageUploadField label="Մինչև" value={form.beforeImage} onChange={(v) => setForm({ ...form, beforeImage: v })} folder="service-gallery" disabled={disabled} />
        <ImageUploadField label="Հետո" value={form.afterImage} onChange={(v) => setForm({ ...form, afterImage: v })} folder="service-gallery" disabled={disabled} />
      </div>
      <TranslatedFields label="Alt (մինչև)" value={form.altBefore} onChange={(v) => setForm({ ...form, altBefore: v })} />
      <TranslatedFields label="Alt (հետո)" value={form.altAfter} onChange={(v) => setForm({ ...form, altAfter: v })} />
      <div className="flex flex-wrap gap-4">
        <label className="flex items-center gap-2 text-sm text-ink-muted">
          <input type="checkbox" checked={form.clientConsent} disabled={disabled} onChange={(e) => setForm({ ...form, clientConsent: e.target.checked })} className="size-4 rounded border-border-strong accent-primary" />
          Հաճախորդի համաձայնություն կա
        </label>
        <label className="flex items-center gap-2 text-sm text-ink-muted">
          <input type="checkbox" checked={form.isPublished} disabled={disabled} onChange={(e) => setForm({ ...form, isPublished: e.target.checked })} className="size-4 rounded border-border-strong accent-primary" />
          Հրապարակված
        </label>
      </div>
      {!form.clientConsent || !form.isPublished ? (
        <p className="text-xs text-warning">Երկուսն էլ պետք է նշված լինեն, որպեսզի պատկերը հայտնվի կայքում։</p>
      ) : null}
    </>
  );
}

function GalleryCard({ item, services, onChanged }: { item: AdminServiceGalleryItemDto; services: AdminServiceDto[]; onChanged: () => void }) {
  const [form, setForm] = useState<FormState>(() => toFormState(item));
  const [pending, setPending] = useState(false);
  const [error, setError] = useState(false);

  async function save() {
    setPending(true);
    setError(false);
    try {
      const response = await fetch(`/api/admin/service-gallery-items/${item.id}`, {
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
    if (!confirm("Ջնջե՞լ այս պատկերը։")) return;
    setPending(true);
    try {
      const response = await fetch(`/api/admin/service-gallery-items/${item.id}`, { method: "DELETE" });
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
      <p className={labelClasses}>#{item.id}</p>
      <GalleryFields form={form} setForm={setForm} services={services} disabled={pending} />
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

export function GalleryManager({ items, services }: { items: AdminServiceGalleryItemDto[]; services: AdminServiceDto[] }) {
  const router = useRouter();
  const sorted = [...items].sort((a, b) => a.sortOrder - b.sortOrder);
  const [creating, setCreating] = useState(false);
  const [form, setForm] = useState<FormState>(emptyForm());
  const [status, setStatus] = useState<"idle" | "saving" | "error">("idle");

  async function handleCreate(e: React.FormEvent) {
    e.preventDefault();
    setStatus("saving");
    try {
      const response = await fetch("/api/admin/service-gallery-items", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(payloadFrom(form)),
      });
      if (!response.ok) {
        setStatus("error");
        return;
      }
      setCreating(false);
      setForm(emptyForm());
      router.refresh();
    } catch {
      setStatus("error");
    }
  }

  return (
    <div className="space-y-4">
      {sorted.map((item) => (
        <GalleryCard key={item.id} item={item} services={services} onChanged={() => router.refresh()} />
      ))}
      {sorted.length === 0 ? (
        <p className="rounded-lg border border-dashed border-border-strong bg-surface px-4 py-6 text-center text-sm text-ink-muted">Դեռ չկան «մինչև/հետո» օրինակներ։</p>
      ) : null}

      {creating ? (
        <form onSubmit={handleCreate} className="space-y-3 rounded-lg border border-primary-light bg-primary-light/30 p-4">
          <GalleryFields form={form} setForm={setForm} services={services} />
          <div className="flex items-center gap-3">
            <button type="submit" disabled={status === "saving"} className={buttonClasses("primary", "md")}>
              Ավելացնել
            </button>
            <button type="button" onClick={() => setCreating(false)} className="text-sm text-ink-muted hover:text-ink">
              Չեղարկել
            </button>
            {status === "error" ? <span className="text-sm text-danger">Չհաջողվեց ավելացնել։</span> : null}
          </div>
        </form>
      ) : (
        <button type="button" onClick={() => setCreating(true)} className={buttonClasses("secondary", "md")}>
          + Ավելացնել օրինակ
        </button>
      )}
    </div>
  );
}
