"use client";

import { useState } from "react";
import { useRouter } from "next/navigation";
import type { AdminServiceCategoryDto, AdminTranslated } from "@/lib/adminApi";
import { TranslatedFields, emptyTranslated, toTranslated, fieldClasses, labelClasses } from "./shared";
import { Badge, buttonClasses } from "../_components/ui";

type FormState = { slug: string; icon: string; sortOrder: string; name: AdminTranslated; description: AdminTranslated };

function toFormState(item: AdminServiceCategoryDto): FormState {
  return {
    slug: item.slug,
    icon: item.icon ?? "",
    sortOrder: String(item.sortOrder),
    name: toTranslated(item.name),
    description: toTranslated(item.description),
  };
}

function payloadFrom(form: FormState) {
  return {
    slug: form.slug.trim(),
    icon: form.icon.trim() || null,
    sort_order: Number(form.sortOrder) || 0,
    name: form.name,
    description: form.description,
  };
}

function CategoryCard({ item, onChanged }: { item: AdminServiceCategoryDto; onChanged: () => void }) {
  const [form, setForm] = useState<FormState>(() => toFormState(item));
  const [pending, setPending] = useState(false);
  const [error, setError] = useState(false);

  async function save() {
    setPending(true);
    setError(false);
    try {
      const response = await fetch(`/api/admin/service-categories/${item.id}`, {
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

  return (
    <div className="space-y-3 rounded-lg border border-border bg-surface p-4">
      <div className="flex flex-wrap items-center gap-3">
        <Badge>{item.slug}</Badge>
        {item.servicesCount != null ? (
          <span className="text-xs text-ink-faint">{item.servicesCount} ծառայություն</span>
        ) : null}
      </div>
      <div className="grid gap-2 sm:grid-cols-3">
        <div className="space-y-1.5">
          <p className={labelClasses}>Slug</p>
          <input value={form.slug} disabled={pending} onChange={(e) => setForm({ ...form, slug: e.target.value })} className={fieldClasses} />
        </div>
        <div className="space-y-1.5">
          <p className={labelClasses}>Icon</p>
          <input value={form.icon} disabled={pending} onChange={(e) => setForm({ ...form, icon: e.target.value })} className={fieldClasses} />
        </div>
        <div className="space-y-1.5">
          <p className={labelClasses}>Կարգ</p>
          <input type="number" value={form.sortOrder} disabled={pending} onChange={(e) => setForm({ ...form, sortOrder: e.target.value })} className={fieldClasses} />
        </div>
      </div>
      <TranslatedFields label="Անվանում" value={form.name} onChange={(v) => setForm({ ...form, name: v })} />
      <TranslatedFields label="Նկարագրություն" value={form.description} onChange={(v) => setForm({ ...form, description: v })} multiline />
      <div className="flex items-center gap-3">
        <button type="button" onClick={save} disabled={pending} className={buttonClasses("primary", "sm")}>
          Պահպանել
        </button>
        {error ? <span className="text-xs text-danger">Չհաջողվեց պահպանել։</span> : null}
      </div>
    </div>
  );
}

export function ServiceCategoriesManager({ items }: { items: AdminServiceCategoryDto[] }) {
  const router = useRouter();
  const sorted = [...items].sort((a, b) => a.sortOrder - b.sortOrder);
  const [creating, setCreating] = useState(false);
  const [newForm, setNewForm] = useState<FormState>({ slug: "", icon: "", sortOrder: "0", name: emptyTranslated(), description: emptyTranslated() });
  const [status, setStatus] = useState<"idle" | "saving" | "error">("idle");

  async function handleCreate(e: React.FormEvent) {
    e.preventDefault();
    setStatus("saving");
    try {
      const response = await fetch("/api/admin/service-categories", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(payloadFrom(newForm)),
      });
      if (!response.ok) {
        setStatus("error");
        return;
      }
      setCreating(false);
      setNewForm({ slug: "", icon: "", sortOrder: "0", name: emptyTranslated(), description: emptyTranslated() });
      router.refresh();
    } catch {
      setStatus("error");
    }
  }

  return (
    <div className="space-y-4">
      {sorted.map((item) => (
        <CategoryCard key={item.id} item={item} onChanged={() => router.refresh()} />
      ))}

      {creating ? (
        <form onSubmit={handleCreate} className="space-y-3 rounded-lg border border-primary-light bg-primary-light/30 p-4">
          <div className="grid gap-2 sm:grid-cols-3">
            <input value={newForm.slug} onChange={(e) => setNewForm({ ...newForm, slug: e.target.value })} placeholder="slug" required className={fieldClasses} />
            <input value={newForm.icon} onChange={(e) => setNewForm({ ...newForm, icon: e.target.value })} placeholder="icon" className={fieldClasses} />
            <input type="number" value={newForm.sortOrder} onChange={(e) => setNewForm({ ...newForm, sortOrder: e.target.value })} className={fieldClasses} />
          </div>
          <TranslatedFields label="Անվանում" value={newForm.name} onChange={(v) => setNewForm({ ...newForm, name: v })} />
          <TranslatedFields label="Նկարագրություն" value={newForm.description} onChange={(v) => setNewForm({ ...newForm, description: v })} multiline />
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
          + Ավելացնել կատեգորիա
        </button>
      )}
    </div>
  );
}
