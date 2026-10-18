"use client";

import { useRouter } from "next/navigation";
import { useState } from "react";
import { cn, Input } from "../../_components/ui";

/** The price the client actually paid; analytics revenue is built on it. Saves on blur or Enter. */
export function OrderPriceInput({
  leadId,
  finalPrice,
  placeholder,
}: {
  leadId: number;
  finalPrice: number | null;
  placeholder?: string;
}) {
  const router = useRouter();
  const [value, setValue] = useState(finalPrice === null ? "" : String(finalPrice));
  const [state, setState] = useState<"idle" | "saving" | "error">("idle");

  async function save() {
    const trimmed = value.trim();
    const next = trimmed === "" ? null : Number(trimmed);
    if (next === finalPrice) return;
    if (next !== null && (!Number.isInteger(next) || next < 0)) {
      setState("error");
      return;
    }

    setState("saving");
    const response = await fetch(`/api/admin/leads/${leadId}`, {
      method: "PATCH",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ final_price: next }),
    }).catch(() => null);

    if (response?.ok) {
      setState("idle");
      router.refresh();
    } else {
      setState("error");
    }
  }

  return (
    <Input
      type="number"
      inputMode="numeric"
      min={0}
      step={100}
      value={value}
      placeholder={placeholder ?? "դր."}
      title="Վերջնական գին (դր.)"
      aria-label="Վերջնական գին"
      disabled={state === "saving"}
      onChange={(e) => {
        setValue(e.target.value);
        setState("idle");
      }}
      onBlur={save}
      onKeyDown={(e) => {
        if (e.key === "Enter") e.currentTarget.blur();
      }}
      className={cn("w-28", state === "error" && "border-danger")}
    />
  );
}
