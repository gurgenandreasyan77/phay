"use client";

import { useState } from "react";
import { useRouter } from "next/navigation";
import type { AdminServiceDto, AdminServiceVideoDto, AdminTranslated, SiteVideoKind } from "@/lib/adminApi";
import { TranslatedFields, emptyTranslated, toTranslated, fieldClasses, labelClasses } from "../services/shared";
import { ImageUploadField } from "../services/ImageUploadField";
import { Badge, buttonClasses } from "@/ui";

export const VIDEO_KIND_LABELS: Record<SiteVideoKind, string> = {
  before_after: "Մինչև/հետո",
  info: "Տեղեկատվական",
};

type FormState = {
  kind: SiteVideoKind;
  serviceId: string;
  title: AdminTranslated;
  videoPath: string;
  posterImage: string;
  sortOrder: string;
  isPublished: boolean;
};

function toFormState(item: AdminServiceVideoDto): FormState {
  return {
    kind: item.kind,
    serviceId: item.serviceId != null ? String(item.serviceId) : "",
    title: toTranslated(item.title),
    videoPath: item.videoPath,
    posterImage: item.posterImage ?? "",
    sortOrder: String(item.sortOrder),
    isPublished: item.isPublished,
  };
}

function emptyForm(): FormState {
  return { kind: "before_after", serviceId: "", title: emptyTranslated(), videoPath: "", posterImage: "", sortOrder: "0", isPublished: false };
}

function payloadFrom(form: FormState) {
  return {
    kind: form.kind,
    service_id: form.serviceId ? Number(form.serviceId) : null,
    title: form.title,
    video_path: form.videoPath.trim(),
    poster_image: form.posterImage.trim() || null,
    sort_order: Number(form.sortOrder) || 0,
    is_published: form.isPublished,
  };
}

function VideoFields({ form, setForm, services, disabled }: {
  form: FormState;
  setForm: (f: FormState) => void;
  services: AdminServiceDto[];
  disabled?: boolean;
}) {
  return (
    <>
      <div className="grid gap-2 sm:grid-cols-4">
        <select value={form.kind} disabled={disabled} onChange={(e) => setForm({ ...form, kind: e.target.value as SiteVideoKind })} className={fieldClasses}>
          {(Object.keys(VIDEO_KIND_LABELS) as SiteVideoKind[]).map((kind) => (
            <option key={kind} value={kind}>{VIDEO_KIND_LABELS[kind]}</option>
          ))}
        </select>
        <select value={form.serviceId} disabled={disabled} onChange={(e) => setForm({ ...form, serviceId: e.target.value })} className={fieldClasses}>
          <option value="">— ընդհանուր (ոչ մեկին կապված) —</option>
          {services.map((s) => (
            <option key={s.id} value={s.id}>{s.name.hy ?? s.slug}</option>
          ))}
        </select>
        <input type="number" value={form.sortOrder} disabled={disabled} onChange={(e) => setForm({ ...form, sortOrder: e.target.value })} placeholder="Կարգ" className={fieldClasses} />
        <label className="flex items-center gap-2 text-sm text-ink-muted">
          <input type="checkbox" checked={form.isPublished} disabled={disabled} onChange={(e) => setForm({ ...form, isPublished: e.target.checked })} className="size-4 rounded border-border-strong accent-primary" />
          Հրապարակված
        </label>
      </div>
      <TranslatedFields label="Վերնագիր (ոչ պարտադիր)" value={form.title} onChange={(v) => setForm({ ...form, title: v })} />
      <ImageUploadField
        label="Տեսանյութ (mp4/webm/mov, մինչև 80 ՄԲ)"
        value={form.videoPath}
        onChange={(v) => setForm({ ...form, videoPath: v })}
        folder="service-videos"
        accept="video/mp4,video/webm,video/quicktime"
        disabled={disabled}
      />
      <ImageUploadField
        label="Շապիկի նկար (ոչ պարտադիր)"
        value={form.posterImage}
        onChange={(v) => setForm({ ...form, posterImage: v })}
        folder="service-gallery"
        disabled={disabled}
      />
    </>
  );
}

function VideoCard({ item, services, onChanged }: { item: AdminServiceVideoDto; services: AdminServiceDto[]; onChanged: () => void }) {
  const [form, setForm] = useState<FormState>(() => toFormState(item));
  const [pending, setPending] = useState(false);
  const [error, setError] = useState(false);

  async function save() {
    setPending(true);
    setError(false);
    try {
      const response = await fetch(`/api/admin/service-videos/${item.id}`, {
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
    if (!confirm("Ջնջե՞լ այս տեսանյութը։")) return;
    setPending(true);
    try {
      const response = await fetch(`/api/admin/service-videos/${item.id}`, { method: "DELETE" });
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
      <div className="flex items-center gap-2">
        <p className={labelClasses}>#{item.id}</p>
        <Badge tone={item.kind === "before_after" ? "success" : "default"}>{VIDEO_KIND_LABELS[item.kind]}</Badge>
      </div>
      <VideoFields form={form} setForm={setForm} services={services} disabled={pending} />
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

export function VideosManager({ items, services }: { items: AdminServiceVideoDto[]; services: AdminServiceDto[] }) {
  const router = useRouter();
  const sorted = [...items].sort((a, b) => a.sortOrder - b.sortOrder);
  const [creating, setCreating] = useState(false);
  const [form, setForm] = useState<FormState>(emptyForm());
  const [status, setStatus] = useState<"idle" | "saving" | "error">("idle");

  async function handleCreate(e: React.FormEvent) {
    e.preventDefault();
    setStatus("saving");
    try {
      const response = await fetch("/api/admin/service-videos", {
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
        <VideoCard key={item.id} item={item} services={services} onChanged={() => router.refresh()} />
      ))}
      {sorted.length === 0 ? (
        <p className="rounded-lg border border-dashed border-border-strong bg-surface px-4 py-6 text-center text-sm text-ink-muted">Դեռ չկան տեսանյութեր։</p>
      ) : null}

      {creating ? (
        <form onSubmit={handleCreate} className="space-y-3 rounded-lg border border-primary-light bg-primary-light/30 p-4">
          <VideoFields form={form} setForm={setForm} services={services} />
          <div className="flex items-center gap-3">
            <button type="submit" disabled={status === "saving"} className={buttonClasses("primary", "md")}>
              Ավելացնել
            </button>
            <button type="button" onClick={() => setCreating(false)} className="text-sm text-ink-muted hover:text-ink">
              Չեղարկել
            </button>
            {status === "error" ? <span className="text-sm text-danger">Չհաջողվեց ավելացնել։ Ստուգեք ֆայլի ձևաչափը/չափը (մինչև 80 ՄԲ)։</span> : null}
          </div>
        </form>
      ) : (
        <button type="button" onClick={() => setCreating(true)} className={buttonClasses("secondary", "md")}>
          + Ավելացնել տեսանյութ
        </button>
      )}
    </div>
  );
}
