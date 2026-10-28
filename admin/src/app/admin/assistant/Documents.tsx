"use client";

import { Download, FileArchive, FileText } from "lucide-react";
import type { AssistantAttachmentDto } from "@/lib/adminApi";
import { cn } from "@/ui";
import { attachmentUrl } from "./Attachments";

const KB = 1024;

function formatSize(bytes: number): string {
  if (bytes >= KB * KB) return `${(bytes / KB / KB).toFixed(1)} ՄԲ`;
  return `${Math.max(1, Math.round(bytes / KB))} ԿԲ`;
}

/** «Word · 12 փաստաթուղթ · 80 ԿԲ» */
function describe(file: AssistantAttachmentDto): string {
  const zip = file.format === "zip";
  const kind = zip ? "ZIP" : file.format === "docx" ? "Word" : file.format === "odt" ? "ODT" : (file.format ?? "").toUpperCase();
  const count = file.documents ? (zip ? `${file.documents} փաստաթուղթ` : `${file.documents} փաստաթուղթ մեկ ֆայլում`) : null;
  return [kind, count, formatSize(file.size)].filter(Boolean).join(" · ");
}

/**
 * The documents an agent made in its answer: a Word file, a text, or for
 * many copies a ZIP and one Word file with all of them to print. Each is
 * downloaded with its button; the name is the file's name.
 */
export function DocumentList({ documents }: { documents: AssistantAttachmentDto[] }) {
  if (documents.length === 0) return null;
  return (
    <ul className="mt-3 grid max-w-md gap-2" aria-label="Փաստաթղթեր">
      {documents.map((file) => {
        const zip = file.format === "zip";
        const Icon = zip ? FileArchive : FileText;
        return (
          <li key={file.id}>
            <a
              href={`${attachmentUrl(file.id)}?download=1`}
              download={file.name}
              className="group flex min-h-16 items-center gap-3 rounded-md border border-[var(--tint-soft)] bg-surface p-2.5 text-ink transition-shadow hover:shadow-md focus-visible:ring-2 focus-visible:ring-[var(--tint)] focus-visible:outline-none"
              title={`Ներբեռնել ${file.name}`}
            >
              <span
                className={cn(
                  "relative flex h-11 w-9 shrink-0 items-center justify-center rounded-[4px] text-white shadow-sm",
                  zip ? "bg-[#b47a2b]" : "bg-[#2f62c4]",
                )}
                aria-hidden="true"
              >
                <Icon className="h-5 w-5" />
                {/* A folded corner: a sheet of paper. */}
                <span className="absolute top-0 right-0 h-2.5 w-2.5 rounded-bl-[3px] bg-white/45" />
              </span>
              <span className="min-w-0 flex-1">
                <span className="line-clamp-2 text-sm font-medium break-words">{file.name}</span>
                <span className="mt-0.5 block text-xs text-ink-muted">{describe(file)}</span>
              </span>
              <span className="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-[var(--tint)] text-white transition-transform group-hover:scale-105 sm:h-9 sm:w-9">
                <Download className="h-4 w-4" aria-hidden="true" />
              </span>
            </a>
          </li>
        );
      })}
    </ul>
  );
}
