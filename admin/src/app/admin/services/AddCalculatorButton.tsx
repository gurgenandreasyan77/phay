"use client";

import { useState } from "react";
import { useRouter } from "next/navigation";
import { Button } from "../_components/ui";

/** Adds the price calculator to the business's services, with starting rates to edit. */
export function AddCalculatorButton() {
  const router = useRouter();
  const [pending, setPending] = useState(false);
  const [error, setError] = useState(false);

  async function add() {
    setPending(true);
    setError(false);
    try {
      const response = await fetch("/api/admin/calculator-settings", { method: "POST" });
      if (!response.ok) {
        setError(true);
        return;
      }
      router.refresh();
    } catch {
      setError(true);
    } finally {
      setPending(false);
    }
  }

  return (
    <div className="flex flex-wrap items-center gap-3">
      <Button onClick={add} disabled={pending}>
        {pending ? "Ավելացվում է…" : "Ավելացնել հաշվիչ"}
      </Button>
      {error ? <span className="text-sm text-danger">Չհաջողվեց ավելացնել։</span> : null}
    </div>
  );
}
