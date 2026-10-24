"use client";

import { useRouter } from "next/navigation";
import { useState } from "react";
import type { LeadStatus } from "@/lib/adminApi";
import { Select } from "@/ui";

export function OrderStatusSelect({
  leadId,
  status,
  statusLabels,
}: {
  leadId: number;
  status: LeadStatus;
  statusLabels: Record<LeadStatus, string>;
}) {
  const router = useRouter();
  const [pending, setPending] = useState(false);

  async function handleChange(newStatus: string) {
    setPending(true);
    try {
      await fetch(`/api/admin/leads/${leadId}`, {
        method: "PATCH",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ status: newStatus }),
      });
      router.refresh();
    } finally {
      setPending(false);
    }
  }

  return (
    <Select
      value={status}
      disabled={pending}
      onChange={(e) => handleChange(e.target.value)}
      className="w-auto"
    >
      {(Object.keys(statusLabels) as LeadStatus[]).map((s) => (
        <option key={s} value={s}>
          {statusLabels[s]}
        </option>
      ))}
    </Select>
  );
}
