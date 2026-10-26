"use client";

import { useRef, useState } from "react";
import { useRouter } from "next/navigation";
import { ImagePlus, Trash2 } from "lucide-react";
import type { BrandAssetDto } from "@/lib/adminApi";
import { Button, FieldLabel, cn } from "@/ui";

/** Examples kept at most (BrandAsset::MAX_EXAMPLES in the API). */
const MAX_EXAMPLES = 6;
const IMAGE_ACCEPT = "image/jpeg,image/png,image/webp,image/gif";

function fileUrl(id: number) {
  return `/api/admin/agents/brand-assets/${id}/file`;
}

/**
 * The pictures the AI agents look at: the business's logo and examples it
 * likes (posts, ads, photos). Uploaded here, or kept from the chat with
 * «Պահել բրենդում»; private to the business.
 */
export function BrandAssets({ assets }: { assets: BrandAssetDto[] }) {
  const router = useRouter();
  const logo = assets.find((a) => a.kind === "logo") ?? null;
  const examples = assets.filter((a) => a.kind === "example");
  const [busy, setBusy] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);
  const logoInput = useRef<HTMLInputElement>(null);
  const exampleInput = useRef<HTMLInputElement>(null);

  async function upload(kind: "logo" | "example", file: File | undefined) {
    if (!file) return;
    setBusy(kind);
    setError(null);
    const body = new FormData();
    body.append("file", file);
    body.append("kind", kind);
    const response = await fetch("/api/admin/agents/brand-assets", { method: "POST", body }).catch(() => null);
    setBusy(null);
    if (!response?.ok) {
      const json = await response?.json().catch(() => null);
      setError(
        json?.errors
          ? (Object.values(json.errors as Record<string, string[]>)[0]?.[0] ?? null)
          : "Չհաջողվեց պահել նկարը։ Փորձեք կրկին։",
      );
      return;
    }
    router.refresh();
  }

  async function remove(asset: BrandAssetDto) {
    setBusy(`delete-${asset.id}`);
    setError(null);
    const response = await fetch(`/api/admin/agents/brand-assets/${asset.id}`, { method: "DELETE" }).catch(() => null);
    setBusy(null);
    if (!response?.ok) {
      setError("Չհաջողվեց հեռացնել։ Փորձեք կրկին։");
      return;
    }
    router.refresh();
  }

  return (
    <div className="space-y-3">
      <div>
        <FieldLabel>Լոգո և օրինակներ</FieldLabel>
        <p className="mt-1 text-xs text-ink-muted">
          AI թիմը նայում է դրանց, երբ գրում է գրառումներ կամ պատրաստում դիզայնի բրիֆ։ Նկարները կարող եք կցել նաև զրույցում և
          սեղմել «Պահել բրենդում»։
        </p>
      </div>

      <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5 xl:grid-cols-7">
        <Tile
          label="Լոգո"
          asset={logo}
          busy={busy === "logo" || busy === `delete-${logo?.id}`}
          onAdd={() => logoInput.current?.click()}
          onRemove={logo ? () => remove(logo) : undefined}
          addLabel={logo ? "Փոխել" : "Ավելացնել լոգո"}
        />
        {examples.map((asset) => (
          <Tile key={asset.id} label="Օրինակ" asset={asset} busy={busy === `delete-${asset.id}`} onRemove={() => remove(asset)} />
        ))}
        {examples.length < MAX_EXAMPLES ? (
          <Tile
            label="Օրինակ"
            asset={null}
            busy={busy === "example"}
            onAdd={() => exampleInput.current?.click()}
            addLabel="Ավելացնել օրինակ"
          />
        ) : null}
      </div>

      <input
        ref={logoInput}
        type="file"
        accept={IMAGE_ACCEPT}
        hidden
        onChange={(e) => upload("logo", e.target.files?.[0]).finally(() => (e.target.value = ""))}
      />
      <input
        ref={exampleInput}
        type="file"
        accept={IMAGE_ACCEPT}
        hidden
        onChange={(e) => upload("example", e.target.files?.[0]).finally(() => (e.target.value = ""))}
      />
      {error ? <p className="text-xs text-danger">{error}</p> : null}
    </div>
  );
}

function Tile({
  label,
  asset,
  busy,
  onAdd,
  onRemove,
  addLabel,
}: {
  label: string;
  asset: BrandAssetDto | null;
  busy: boolean;
  onAdd?: () => void;
  onRemove?: () => void;
  addLabel?: string;
}) {
  return (
    <div className="space-y-1.5">
      <div
        className={cn(
          "relative aspect-square overflow-hidden rounded-lg border bg-surface-secondary/50",
          asset ? "border-border" : "border-dashed border-border",
          busy && "opacity-60",
        )}
      >
        {asset ? (
          <a
            href={fileUrl(asset.id)}
            target="_blank"
            rel="noopener"
            title={asset.note ?? asset.name}
            className="block h-full w-full"
          >
            {/* eslint-disable-next-line @next/next/no-img-element -- a private file through the BFF */}
            <img
              src={fileUrl(asset.id)}
              alt={asset.note ?? asset.name}
              className="h-full w-full object-contain p-2"
              loading="lazy"
            />
          </a>
        ) : (
          <button
            type="button"
            onClick={onAdd}
            disabled={busy}
            className="flex h-full w-full flex-col items-center justify-center gap-1 p-2 text-center text-xs text-ink-muted hover:text-ink"
          >
            <ImagePlus className="h-5 w-5" aria-hidden="true" />
            {busy ? "Պահվում է…" : addLabel}
          </button>
        )}
      </div>
      <div className="flex items-center justify-between gap-1">
        <span className="truncate text-xs text-ink-muted">{label}</span>
        {asset ? (
          <span className="flex shrink-0">
            {onAdd ? (
              <Button
                type="button"
                size="sm"
                variant="ghost"
                className="w-10 px-0! sm:w-8"
                onClick={onAdd}
                disabled={busy}
                aria-label={addLabel}
                title={addLabel}
              >
                <ImagePlus className="h-4 w-4" />
              </Button>
            ) : null}
            {onRemove ? (
              <Button
                type="button"
                size="sm"
                variant="ghost"
                className="w-10 px-0! sm:w-8"
                onClick={onRemove}
                disabled={busy}
                aria-label={`Հեռացնել ${asset.name}`}
              >
                <Trash2 className="h-4 w-4" />
              </Button>
            ) : null}
          </span>
        ) : null}
      </div>
    </div>
  );
}
