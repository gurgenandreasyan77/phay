"use client";

import { Check, Download } from "lucide-react";
import type { AssistantAttachmentDto } from "@/lib/adminApi";
import { cn } from "@/ui";
import { attachmentUrl } from "./Attachments";

/** What the owner sends when they pick a variant: the agent finds it by its number. */
export function chooseMessage(design: AssistantAttachmentDto, position: number): string {
  return `Ընտրում եմ ${position}-րդ տարբերակը՝ «${design.title ?? design.name}» (design #${design.id})։ Պատրաստիր վերջնականը։`;
}

/**
 * The pictures an agent made in its answer. Several are variants to choose
 * from: each has «Ընտրել», which asks the agent to finish that one. Every
 * picture opens full size and downloads as a PNG.
 */
export function DesignGallery({
  designs,
  onChoose,
  chosenId = null,
}: {
  designs: AssistantAttachmentDto[];
  /** Only on the latest answer, while the chat can be asked. */
  onChoose?: (design: AssistantAttachmentDto, position: number) => void;
  /** The variant already picked in the next question. */
  chosenId?: number | null;
}) {
  if (designs.length === 0) return null;
  const variants = designs.length > 1;
  // Variants share a format; a post and its story are one finished piece, nothing to choose.
  const choosing = variants && new Set(designs.map((d) => d.format)).size === 1;

  return (
    <div
      className={cn(
        "-mx-1 mt-3",
        // On a phone the variants scroll sideways, one and a bit on screen.
        variants
          ? "flex snap-x snap-mandatory gap-3 overflow-x-auto overscroll-x-contain px-1 pb-2 sm:grid sm:grid-cols-3 sm:items-start sm:overflow-visible sm:pb-0"
          : "max-w-xs px-1",
      )}
      aria-label={choosing ? "Տարբերակներ" : "Նկարներ"}
    >
      {designs.map((design, i) => {
        const chosen = chosenId === design.id;
        return (
          <figure
            key={design.id}
            className={cn(
              "flex shrink-0 snap-start flex-col overflow-hidden rounded-md border bg-surface",
              variants && "w-[72%] sm:w-auto",
              chosen ? "border-[var(--tint)] ring-2 ring-[var(--tint)]" : "border-[var(--tint-soft)]",
            )}
          >
            <a
              href={attachmentUrl(design.id)}
              target="_blank"
              rel="noopener"
              className="relative block bg-black/5"
              title="Բացել ամբողջ չափով"
              style={design.width && design.height ? { aspectRatio: `${design.width} / ${design.height}` } : undefined}
            >
              {/* eslint-disable-next-line @next/next/no-img-element -- a private file through the BFF */}
              <img
                src={attachmentUrl(design.id)}
                alt={design.title ?? design.name}
                className="h-full w-full object-contain"
                loading="lazy"
              />
              {choosing ? (
                <span className="absolute top-2 left-2 flex h-7 min-w-7 items-center justify-center rounded-full bg-[var(--tint)] px-2 text-xs font-semibold text-white shadow">
                  {i + 1}
                </span>
              ) : null}
            </a>
            <figcaption className="flex flex-1 flex-col gap-2 p-2">
              <p className="line-clamp-2 text-xs font-medium text-ink">{design.title ?? design.name}</p>
              <div className="mt-auto flex items-center gap-1.5">
                {choosing && onChoose ? (
                  <button
                    type="button"
                    onClick={() => onChoose(design, i + 1)}
                    className="flex min-h-10 flex-1 items-center justify-center gap-1 rounded-md bg-[var(--tint)] px-2 text-xs font-medium text-white hover:opacity-90 sm:min-h-8"
                  >
                    <Check className="h-3.5 w-3.5" aria-hidden="true" />
                    Ընտրել
                  </button>
                ) : chosen ? (
                  <span className="flex min-h-10 flex-1 items-center gap-1 text-xs font-medium text-[var(--tint)] sm:min-h-8">
                    <Check className="h-3.5 w-3.5" aria-hidden="true" />
                    Ընտրված է
                  </span>
                ) : (
                  <span className="flex-1" />
                )}
                <a
                  href={`${attachmentUrl(design.id)}?download=1`}
                  download={design.name}
                  className="flex min-h-10 min-w-10 items-center justify-center rounded-md border border-border text-ink-muted hover:bg-surface-secondary hover:text-ink sm:min-h-8 sm:min-w-8"
                  aria-label={`Ներբեռնել ${design.title ?? design.name}`}
                  title="Ներբեռնել PNG"
                >
                  <Download className="h-4 w-4" aria-hidden="true" />
                </a>
              </div>
            </figcaption>
          </figure>
        );
      })}
    </div>
  );
}
