"use client";

import { useState } from "react";

const fieldClasses =
  "w-full rounded-md border border-border bg-surface px-2.5 py-1.5 text-sm text-ink placeholder:text-ink-faint transition-colors focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary-light disabled:cursor-not-allowed disabled:bg-surface-secondary disabled:text-ink-faint";
const labelClasses = "text-xs font-medium text-ink-muted";

/**
 * Pairs a plain text "path" input (what every image field in this admin
 * ultimately stores — see ProductEditor's media tab) with a real upload
 * button that fills it in, using the new Admin\UploadController endpoint.
 * The text input stays editable directly too, so an admin can still paste a
 * path by hand if they'd rather.
 */
export function ImageUploadField({
  label,
  value,
  onChange,
  folder,
  accept = "image/jpeg,image/png,image/webp",
  disabled,
}: {
  label: string;
  value: string;
  onChange: (path: string) => void;
  folder: "services" | "service-packages" | "service-gallery" | "service-videos" | "additional-services" | "posts";
  accept?: string;
  disabled?: boolean;
}) {
  const [uploading, setUploading] = useState(false);
  const [error, setError] = useState(false);

  async function handleFile(file: File) {
    setUploading(true);
    setError(false);
    try {
      const formData = new FormData();
      formData.append("file", file);
      formData.append("folder", folder);

      const response = await fetch("/api/admin/uploads", { method: "POST", body: formData });
      if (!response.ok) {
        setError(true);
        return;
      }

      const json = (await response.json()) as { path?: string };
      if (json.path) onChange(json.path);
    } catch {
      setError(true);
    } finally {
      setUploading(false);
    }
  }

  return (
    <div className="space-y-1.5">
      <p className={labelClasses}>{label}</p>
      <div className="flex gap-2">
        <input value={value} disabled={disabled} onChange={(e) => onChange(e.target.value)} className={fieldClasses} />
        <label className="flex shrink-0 cursor-pointer items-center rounded-md border border-border px-3 text-xs font-medium text-ink hover:bg-surface-secondary">
          {uploading ? "…" : "Վերբեռնել"}
          <input
            type="file"
            accept={accept}
            disabled={disabled || uploading}
            className="hidden"
            onChange={(e) => {
              const file = e.target.files?.[0];
              if (file) handleFile(file);
              e.target.value = "";
            }}
          />
        </label>
      </div>
      {error ? <p className="text-xs text-danger">Վերբեռնումը ձախողվեց։</p> : null}
    </div>
  );
}
