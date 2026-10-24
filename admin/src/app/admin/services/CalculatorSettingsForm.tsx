"use client";

import { useState } from "react";
import { useRouter } from "next/navigation";
import type { CalculatorSettingsDto } from "@/lib/api";
import { Button, FieldLabel, FormStatus, Input } from "@/ui";

const PROPERTY_TYPES: { key: "apartment" | "house" | "office"; label: string }[] = [
  { key: "apartment", label: "Բնակարան" },
  { key: "house", label: "Առանձնատուն" },
  { key: "office", label: "Գրասենյակ" },
];

const CLEANING_TYPES: { key: "regular" | "general" | "postConstruction"; label: string }[] = [
  { key: "regular", label: "Կանոնավոր" },
  { key: "general", label: "Գեներալ" },
  { key: "postConstruction", label: "Վերանորոգումից հետո" },
];

const ROOM_TYPES: { key: keyof CalculatorSettingsDto["roomRates"]; label: string }[] = [
  { key: "studio", label: "Ստուդիո" },
  { key: "oneRoom", label: "1-սենյականոց" },
  { key: "twoRoom", label: "2-սենյականոց" },
  { key: "threeRoom", label: "3-սենյականոց" },
  { key: "fourPlusRoom", label: "4+ սենյականոց" },
];

const DRY_CLEANING_LABELS: Record<keyof CalculatorSettingsDto["dryCleaningRates"], string> = {
  sofa2: "Երկտեղանի բազմոց (հատ)",
  sofa3: "Երեքտեղանի բազմոց (հատ)",
  sofaCorner: "Անկյունային բազմոց (հատ)",
  armchair: "Բազկաթոռ (հատ)",
  chair: "Աթոռ (հատ)",
  mattressSingle: "Մեկտեղանի ներքնակ (հատ)",
  mattressDouble: "Երկտեղանի ներքնակ (հատ)",
  carpet: "Գորգ (մ²)",
  steam: "Գոլորշային մաքրում (սենյակ)",
};
const DRY_CLEANING_KEYS = Object.keys(DRY_CLEANING_LABELS) as (keyof CalculatorSettingsDto["dryCleaningRates"])[];

export function CalculatorSettingsForm({ settings }: { settings: CalculatorSettingsDto }) {
  const router = useRouter();
  const [rates, setRates] = useState(settings);
  const [status, setStatus] = useState<"idle" | "saving" | "saved" | "error">("idle");

  function setCleaningRate(property: "apartment" | "house" | "office", cleaning: string, value: number) {
    setRates((r) => ({
      ...r,
      cleaningRates: { ...r.cleaningRates, [property]: { ...r.cleaningRates[property], [cleaning]: value } },
    }));
  }

  function setRoomRate(room: keyof CalculatorSettingsDto["roomRates"], cleaning: string, value: number) {
    setRates((r) => ({
      ...r,
      roomRates: { ...r.roomRates, [room]: { ...r.roomRates[room], [cleaning]: value } },
    }));
  }

  function setDryCleaningRate(key: keyof CalculatorSettingsDto["dryCleaningRates"], value: number) {
    setRates((r) => ({ ...r, dryCleaningRates: { ...r.dryCleaningRates, [key]: value } }));
  }

  function setDiscount(cleaning: "regular" | "general" | "postConstruction", value: number) {
    const clamped = Math.min(100, Math.max(0, value));
    setRates((r) => ({ ...r, cleaningDiscounts: { ...r.cleaningDiscounts, [cleaning]: clamped } }));
  }

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault();
    setStatus("saving");

    try {
      const response = await fetch("/api/admin/calculator-settings", {
        method: "PUT",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          cleaning_rates: rates.cleaningRates,
          room_rates: rates.roomRates,
          cleaning_discounts: rates.cleaningDiscounts,
          bathroom_rate: rates.bathroomRate,
          vitrage_rate: rates.vitrageRate,
          extra_floor_rate: rates.extraFloorRate,
          heavy_soiling_multiplier: rates.heavySoilingMultiplier,
          cleaning_minimum: rates.cleaningMinimum,
          dry_cleaning_rates: rates.dryCleaningRates,
        }),
      });

      if (!response.ok) {
        setStatus("error");
        return;
      }

      setStatus("saved");
      router.refresh();
    } catch {
      setStatus("error");
    }
  }

  return (
    <form onSubmit={handleSubmit} className="space-y-6 rounded-lg border border-border bg-surface p-5">
      <div className="space-y-2">
        <p className="text-sm font-semibold text-ink">Տարածքի սակագներ (֏ / մ²)</p>
        <div className="overflow-x-auto">
          <table className="w-full text-left text-sm">
            <thead className="text-ink-muted">
              <tr>
                <th className="py-1 pr-3" />
                {CLEANING_TYPES.map((c) => (
                  <th key={c.key} className="px-2 py-1 font-medium">
                    {c.label}
                  </th>
                ))}
              </tr>
            </thead>
            <tbody>
              {PROPERTY_TYPES.map((p) => (
                <tr key={p.key}>
                  <td className="py-1 pr-3 font-medium text-ink">{p.label}</td>
                  {CLEANING_TYPES.map((c) => (
                    <td key={c.key} className="px-2 py-1">
                      <Input
                        type="number"
                        value={rates.cleaningRates[p.key][c.key]}
                        onChange={(e) => setCleaningRate(p.key, c.key, Number(e.target.value) || 0)}
                      />
                    </td>
                  ))}
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>

      <div className="space-y-3 rounded-md border border-border bg-surface-secondary p-4">
        <div>
          <p className="text-sm font-semibold text-ink">Զեղչեր ըստ մաքրման տեսակի (%)</p>
          <p className="mt-0.5 text-xs text-ink-muted">
            Զեղչը հանվում է ընտրված տեսակի քառակուսի մետրի գնից և անմիջապես երևում է հաշվիչում ու ծառայությունների
            էջում։
          </p>
        </div>
        <div className="grid gap-3 sm:grid-cols-3">
          {CLEANING_TYPES.map((c) => (
            <div key={c.key} className="space-y-1.5">
              <FieldLabel>{c.label}</FieldLabel>
              <div className="flex items-center gap-1.5">
                <Input
                  type="number"
                  min={0}
                  max={100}
                  value={rates.cleaningDiscounts[c.key]}
                  onChange={(e) => setDiscount(c.key, Number(e.target.value) || 0)}
                />
                <span className="text-sm text-ink-muted">%</span>
              </div>
              {rates.cleaningDiscounts[c.key] > 0 ? (
                <p className="text-xs text-success">
                  {rates.cleaningRates.apartment[c.key]} ֏ →{" "}
                  <span className="font-semibold">
                    {Math.round(rates.cleaningRates.apartment[c.key] * (1 - rates.cleaningDiscounts[c.key] / 100))} ֏
                  </span>{" "}
                  / մ² (բնակարան)
                </p>
              ) : null}
            </div>
          ))}
        </div>
      </div>

      <div className="space-y-2">
        <p className="text-sm font-semibold text-ink">Սենյակների քանակով գներ (֏, «-ից»)</p>
        <div className="overflow-x-auto">
          <table className="w-full text-left text-sm">
            <thead className="text-ink-muted">
              <tr>
                <th className="py-1 pr-3" />
                {CLEANING_TYPES.map((c) => (
                  <th key={c.key} className="px-2 py-1 font-medium">
                    {c.label}
                  </th>
                ))}
              </tr>
            </thead>
            <tbody>
              {ROOM_TYPES.map((room) => (
                <tr key={room.key}>
                  <td className="py-1 pr-3 font-medium text-ink">{room.label}</td>
                  {CLEANING_TYPES.map((c) => (
                    <td key={c.key} className="px-2 py-1">
                      <Input
                        type="number"
                        value={rates.roomRates[room.key][c.key]}
                        onChange={(e) => setRoomRate(room.key, c.key, Number(e.target.value) || 0)}
                      />
                    </td>
                  ))}
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>

      <div className="grid gap-3 sm:grid-cols-4">
        <div className="space-y-1.5">
          <FieldLabel>Սանհանգույցի հավելավճար (֏)</FieldLabel>
          <Input
            type="number"
            value={rates.bathroomRate}
            onChange={(e) => setRates((r) => ({ ...r, bathroomRate: Number(e.target.value) || 0 }))}
          />
        </div>
        <div className="space-y-1.5">
          <FieldLabel>Վիտրաժի սակագին (֏ / մ²)</FieldLabel>
          <Input
            type="number"
            value={rates.vitrageRate}
            onChange={(e) => setRates((r) => ({ ...r, vitrageRate: Number(e.target.value) || 0 }))}
          />
        </div>
        <div className="space-y-1.5">
          <FieldLabel>Հավելյալ հարկի վճար (֏)</FieldLabel>
          <Input
            type="number"
            value={rates.extraFloorRate}
            onChange={(e) => setRates((r) => ({ ...r, extraFloorRate: Number(e.target.value) || 0 }))}
          />
        </div>
        <div className="space-y-1.5">
          <FieldLabel>Նվազագույն պատվեր (֏)</FieldLabel>
          <Input
            type="number"
            value={rates.cleaningMinimum}
            onChange={(e) => setRates((r) => ({ ...r, cleaningMinimum: Number(e.target.value) || 0 }))}
          />
        </div>
        <div className="space-y-1.5">
          <FieldLabel>Ուժեղ կեղտոտվածության հավելավճար (%)</FieldLabel>
          <Input
            type="number"
            step="1"
            value={Math.round(rates.heavySoilingMultiplier * 100)}
            onChange={(e) =>
              setRates((r) => ({ ...r, heavySoilingMultiplier: (Number(e.target.value) || 0) / 100 }))
            }
          />
        </div>
      </div>

      <div className="space-y-2">
        <p className="text-sm font-semibold text-ink">Քիմմաքրման սակագներ (գումարիչ)</p>
        <div className="grid gap-3 sm:grid-cols-3">
          {DRY_CLEANING_KEYS.map((key) => (
            <div key={key} className="space-y-1.5">
              <FieldLabel>{DRY_CLEANING_LABELS[key]}</FieldLabel>
              <Input
                type="number"
                value={rates.dryCleaningRates[key]}
                onChange={(e) => setDryCleaningRate(key, Number(e.target.value) || 0)}
              />
            </div>
          ))}
        </div>
      </div>

      <div className="flex items-center gap-3">
        <Button type="submit" disabled={status === "saving"}>
          {status === "saving" ? "Պահպանվում է…" : "Պահպանել"}
        </Button>
        <FormStatus status={status} />
      </div>
    </form>
  );
}
