"use client";

import { useState } from "react";
import { useRouter } from "next/navigation";
import { Button, Card, FormStatus, Input } from "../_components/ui";

const labelClasses = "text-sm font-medium text-ink";

const LOCALES = [
  { key: "hy" as const, label: "Հայերեն" },
  { key: "ru" as const, label: "Ռուսերեն" },
  { key: "en" as const, label: "Անգլերեն" },
];

export function AddressForm({ address }: { address: Partial<Record<"hy" | "ru" | "en", string>> }) {
  const router = useRouter();
  const [values, setValues] = useState({
    hy: address.hy ?? "",
    ru: address.ru ?? "",
    en: address.en ?? "",
  });
  const [status, setStatus] = useState<"idle" | "saving" | "saved" | "error">("idle");

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault();
    setStatus("saving");

    try {
      const response = await fetch("/api/admin/settings", {
        method: "PUT",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ address: values }),
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
    <Card>
      <form onSubmit={handleSubmit} className="space-y-4">
        <div className="grid gap-4 sm:grid-cols-3">
          {LOCALES.map(({ key, label }) => (
            <div key={key} className="space-y-1.5">
              <label htmlFor={`address-${key}`} className={labelClasses}>
                {label}
              </label>
              <Input
                id={`address-${key}`}
                type="text"
                value={values[key]}
                maxLength={500}
                onChange={(e) => setValues((v) => ({ ...v, [key]: e.target.value }))}
              />
            </div>
          ))}
        </div>

        <div className="flex items-center gap-3">
          <Button type="submit" disabled={status === "saving"}>
            {status === "saving" ? "Պահպանվում է…" : "Պահպանել"}
          </Button>
          <FormStatus status={status} />
        </div>
      </form>
    </Card>
  );
}
