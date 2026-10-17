"use client";

import { useRouter, useSearchParams } from "next/navigation";
import type { LeadStatus } from "@/lib/adminApi";
import { Select } from "../_components/ui";

export function StatusFilter({ statusLabels }: { statusLabels: Record<LeadStatus, string> }) {
  const router = useRouter();
  const searchParams = useSearchParams();
  const current = searchParams.get("status") ?? "";

  function handleChange(value: string) {
    const params = new URLSearchParams(searchParams.toString());
    if (value) {
      params.set("status", value);
    } else {
      params.delete("status");
    }
    router.push(`/admin/orders?${params.toString()}`);
  }

  return (
    <Select value={current} onChange={(e) => handleChange(e.target.value)} className="w-auto">
      <option value="">Բոլոր կարգավիճակները</option>
      {(Object.keys(statusLabels) as LeadStatus[]).map((status) => (
        <option key={status} value={status}>
          {statusLabels[status]}
        </option>
      ))}
    </Select>
  );
}
