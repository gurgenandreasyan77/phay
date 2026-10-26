"use client";

import { useState } from "react";
import { Check, FileText, Film, ImagePlus, Loader2, X } from "lucide-react";
import type { AssistantAttachmentDto } from "@/lib/adminApi";
import { cn } from "@/ui";

/** Files one question may carry (Attachments::MAX_PER_MESSAGE in the API). */
export const MAX_FILES = 4;

/** What the file picker offers; the API checks the content again. */
export const ACCEPT =
  "image/jpeg,image/png,image/webp,image/gif,video/mp4,video/quicktime,video/webm,video/x-m4v,.mov,.m4v,application/pdf,text/plain,text/csv,text/markdown,.txt,.csv,.md";

const MB = 1024 * 1024;
const LIMITS = { image: 5 * MB, pdf: 10 * MB, text: 512 * 1024, video: 80 * MB } as const;
const IMAGE_TYPES = ["image/jpeg", "image/png", "image/webp", "image/gif"];
const VIDEO_EXTENSIONS = ["mp4", "mov", "webm", "m4v"];

export const FILE_ERRORS = {
  type: "Կարելի է կցել նկար (JPG, PNG, WEBP, GIF), տեսանյութ (MP4, MOV, WEBM), PDF կամ տեքստային ֆայլ (TXT, CSV, MD)։",
  image: "Նկարը պետք է լինի մինչև 5 ՄԲ։",
  video: "Տեսանյութը պետք է լինի մինչև 80 ՄԲ։",
  pdf: "PDF-ը պետք է լինի մինչև 10 ՄԲ։",
  text: "Տեքստային ֆայլը պետք է լինի մինչև 512 ԿԲ։",
  tooMany: `Մեկ հարցին կարելի է կցել մինչև ${MAX_FILES} ֆայլ։`,
  upload: "Ֆայլը չհաջողվեց վերբեռնել։ Փորձեք կրկին։",
};

export type FileKind = AssistantAttachmentDto["kind"];

/** A file as the chat shows it: in the box before sending, or in a question. */
export type ShownFile = {
  /** Stable key in the list (the attachment id once uploaded). */
  key: string;
  name: string;
  kind: FileKind;
  size: number;
  /** A picture's preview: a local blob before upload, the API's file after. */
  src: string | null;
  /** Set once the API keeps it. */
  attachment: AssistantAttachmentDto | null;
  status: "uploading" | "ready" | "error";
  error?: string;
};

export function attachmentUrl(id: number): string {
  return `/api/admin/assistant/attachments/${id}/file`;
}

/** The kind of a picked file, or the reason it can't go (checked again by the API, by content). */
export function checkFile(file: File): { kind: FileKind } | { error: string } {
  const extension = file.name.split(".").pop()?.toLowerCase() ?? "";
  const kind: FileKind | null = IMAGE_TYPES.includes(file.type)
    ? "image"
    : file.type.startsWith("video/") || VIDEO_EXTENSIONS.includes(extension)
      ? "video"
      : file.type === "application/pdf" || extension === "pdf"
      ? "pdf"
      : ["txt", "csv", "md"].includes(extension)
        ? "text"
        : null;
  if (!kind) return { error: FILE_ERRORS.type };
  if (file.size > LIMITS[kind]) return { error: FILE_ERRORS[kind] };
  return { kind };
}

/** A kept attachment as the chat shows it. */
export function shownFromAttachment(attachment: AssistantAttachmentDto): ShownFile {
  return {
    key: String(attachment.id),
    name: attachment.name,
    kind: attachment.kind,
    size: attachment.size,
    src: attachment.kind === "image" ? attachmentUrl(attachment.id) : null,
    attachment,
    status: "ready",
  };
}

/** 0:07, 1:25 */
export function formatDuration(seconds: number): string {
  const total = Math.round(seconds);
  return `${Math.floor(total / 60)}:${String(total % 60).padStart(2, "0")}`;
}

function formatSize(bytes: number): string {
  if (bytes >= MB) return `${(bytes / MB).toFixed(1)} ՄԲ`;
  return `${Math.max(1, Math.round(bytes / 1024))} ԿԲ`;
}

/** The files in the question box, each with a way to take it back. */
export function PendingFiles({ files, onRemove }: { files: ShownFile[]; onRemove: (key: string) => void }) {
  if (files.length === 0) return null;
  return (
    <ul className="flex gap-2 overflow-x-auto overscroll-contain pb-1" aria-label="Կցված ֆայլեր">
      {files.map((file) => (
        <li
          key={file.key}
          className={cn(
            "relative flex h-16 w-48 shrink-0 items-center gap-2 rounded-md border bg-surface p-1.5 pr-10",
            file.status === "error" ? "border-danger" : "border-border",
          )}
          title={file.error ?? file.name}
        >
          <Thumb file={file} className="h-12 w-12" />
          <div className="min-w-0 text-xs">
            <p className="truncate font-medium text-ink">{file.name}</p>
            <p className={cn("truncate", file.status === "error" ? "text-danger" : "text-ink-muted")}>
              {file.status === "uploading"
                ? file.kind === "video"
                  ? "Վերբեռնվում և դիտվում է…"
                  : "Վերբեռնվում է…"
                : file.status === "error"
                  ? file.error
                  : file.attachment?.duration
                    ? `${formatDuration(file.attachment.duration)} · ${formatSize(file.size)}`
                    : formatSize(file.size)}
            </p>
          </div>
          {/* The whole right edge is the button: an easy target for a finger. */}
          <button
            type="button"
            onClick={() => onRemove(file.key)}
            className="absolute inset-y-0 right-0 flex w-10 items-center justify-center rounded-r-md text-ink-muted hover:bg-surface-secondary hover:text-ink"
            aria-label={`Հեռացնել ${file.name}`}
          >
            <X className="h-4 w-4" />
          </button>
        </li>
      ))}
    </ul>
  );
}

function Thumb({ file, className }: { file: ShownFile; className?: string }) {
  if (file.status === "uploading") {
    return (
      <span className={cn("flex shrink-0 items-center justify-center rounded bg-surface-secondary", className)}>
        <Loader2 className="h-4 w-4 animate-spin text-ink-muted" aria-hidden="true" />
      </span>
    );
  }
  if (file.kind === "video") {
    return (
      <span className={cn("flex shrink-0 items-center justify-center rounded bg-ink text-white", className)}>
        <Film className="h-5 w-5" aria-hidden="true" />
      </span>
    );
  }
  if (file.kind === "image" && file.src) {
    // eslint-disable-next-line @next/next/no-img-element -- a private file through the BFF, not a static asset
    return <img src={file.src} alt="" className={cn("shrink-0 rounded object-cover", className)} />;
  }
  return (
    <span className={cn("flex shrink-0 items-center justify-center rounded bg-surface-secondary text-ink-muted", className)}>
      <FileText className="h-5 w-5" aria-hidden="true" />
    </span>
  );
}

/**
 * The files of a sent question: pictures as previews (opened full size in
 * a new tab), PDFs and texts as named chips. In an agent's chat a picture
 * can be kept for the team as the logo or an example.
 */
export function MessageFiles({
  files,
  canSaveToBrand = false,
  onLight = false,
}: {
  files: ShownFile[];
  canSaveToBrand?: boolean;
  onLight?: boolean;
}) {
  if (files.length === 0) return null;
  const images = files.filter((f) => f.kind === "image");
  const videos = files.filter((f) => f.kind === "video" && f.attachment);
  const others = files.filter((f) => f.kind !== "image" && f.kind !== "video");

  return (
    <div className="space-y-2">
      {images.length > 0 ? (
        <div className={cn("grid gap-2", images.length > 1 ? "grid-cols-2" : "grid-cols-1")}>
          {images.map((file) => (
            <figure key={file.key} className="space-y-1">
              <a
                href={file.attachment ? attachmentUrl(file.attachment.id) : (file.src ?? "#")}
                target="_blank"
                rel="noopener"
                className="block overflow-hidden rounded-md bg-black/5"
                title={file.name}
              >
                {/* eslint-disable-next-line @next/next/no-img-element -- a private file through the BFF */}
                <img src={file.src ?? ""} alt={file.name} className="max-h-56 w-full object-contain" loading="lazy" />
              </a>
              {canSaveToBrand && file.attachment ? <SaveToBrand attachmentId={file.attachment.id} onLight={onLight} /> : null}
            </figure>
          ))}
        </div>
      ) : null}
      {videos.length > 0 ? (
        <div className={cn("grid gap-2", videos.length > 1 ? "grid-cols-2" : "grid-cols-1")}>
          {videos.map((file) => (
            <figure key={file.key} className="overflow-hidden rounded-md bg-black">
              {/* Only the start is read until it is played (ranges through the BFF); #t shows a frame past the fade-in. */}
              <video
                src={`${attachmentUrl(file.attachment!.id)}#t=0.4`}
                controls
                playsInline
                preload="metadata"
                className="max-h-64 w-full"
                aria-label={file.name}
              />
              <figcaption className="truncate bg-black/80 px-2 py-1 text-[11px] text-white/85">
                {file.name}
                {file.attachment?.duration ? ` · ${formatDuration(file.attachment.duration)}` : ""}
              </figcaption>
            </figure>
          ))}
        </div>
      ) : null}
      {others.map((file) => (
        <a
          key={file.key}
          href={file.attachment ? attachmentUrl(file.attachment.id) : "#"}
          target="_blank"
          rel="noopener"
          className={cn(
            "flex min-h-11 items-center gap-2 rounded-md px-3 py-2 text-xs",
            onLight ? "bg-surface text-ink" : "bg-white/15 text-inherit hover:bg-white/25",
          )}
        >
          <FileText className="h-4 w-4 shrink-0" aria-hidden="true" />
          <span className="min-w-0 flex-1 truncate">{file.name}</span>
          <span className="shrink-0 opacity-75">{formatSize(file.size)}</span>
        </a>
      ))}
    </div>
  );
}

/** «Keep in the brand»: as the logo or as an example, for every agent of the team. */
function SaveToBrand({ attachmentId, onLight }: { attachmentId: number; onLight: boolean }) {
  const [open, setOpen] = useState(false);
  const [state, setState] = useState<"idle" | "saving" | "logo" | "example" | "error">("idle");
  const [error, setError] = useState<string | null>(null);

  async function save(kind: "logo" | "example") {
    setState("saving");
    setError(null);
    const response = await fetch("/api/admin/agents/brand-assets", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ attachment_id: attachmentId, kind }),
    }).catch(() => null);
    if (!response?.ok) {
      const json = await response?.json().catch(() => null);
      setError(json?.errors ? (Object.values(json.errors as Record<string, string[]>)[0]?.[0] ?? null) : null);
      setState("error");
      return;
    }
    setState(kind);
    setOpen(false);
  }

  const tone = onLight ? "text-ink-muted hover:text-ink" : "text-white/85 hover:text-white";

  if (state === "logo" || state === "example") {
    return (
      <p className={cn("flex items-center gap-1 text-xs", onLight ? "text-success" : "text-white")}>
        <Check className="h-3.5 w-3.5" aria-hidden="true" />
        {state === "logo" ? "Պահված է որպես լոգո" : "Պահված է որպես օրինակ"}
      </p>
    );
  }

  return (
    <div className="text-xs">
      {open ? (
        <div className="flex flex-wrap gap-1.5">
          {(["logo", "example"] as const).map((kind) => (
            <button
              key={kind}
              type="button"
              disabled={state === "saving"}
              onClick={() => save(kind)}
              className={cn(
                "min-h-10 rounded-md px-3 font-medium disabled:opacity-50 sm:min-h-8",
                onLight ? "border border-border bg-surface text-ink" : "bg-white/20 text-white hover:bg-white/30",
              )}
            >
              {kind === "logo" ? "Որպես լոգո" : "Որպես օրինակ"}
            </button>
          ))}
          <button type="button" onClick={() => setOpen(false)} className={cn("min-h-10 px-2 sm:min-h-8", tone)}>
            Չեղարկել
          </button>
        </div>
      ) : (
        <button type="button" onClick={() => setOpen(true)} className={cn("flex min-h-10 items-center gap-1 sm:min-h-8", tone)}>
          <ImagePlus className="h-3.5 w-3.5" aria-hidden="true" />
          Պահել բրենդում
        </button>
      )}
      {state === "error" ? (
        <p className={onLight ? "text-danger" : "text-white"}>{error ?? "Չհաջողվեց պահել։ Փորձեք կրկին։"}</p>
      ) : null}
    </div>
  );
}
