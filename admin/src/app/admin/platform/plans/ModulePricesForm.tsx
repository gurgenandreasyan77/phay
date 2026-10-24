"use client";

import { useState, type FormEvent } from "react";
import { useRouter } from "next/navigation";
import type { ModulePriceDto } from "@/lib/adminApi";
import { Button, FormStatus, Input, SectionCard, TableWrap, Td, Th, Thead, Tr } from "@/ui";

/** The monthly price of each module, added when a business has it on. */
export function ModulePricesForm({ modules }: { modules: ModulePriceDto[] }) {
  const router = useRouter();
  const [prices, setPrices] = useState<Record<string, string>>(
    Object.fromEntries(modules.map((m) => [m.key, String(m.price)])),
  );
  const [status, setStatus] = useState<"idle" | "saving" | "saved" | "error">("idle");

  async function submit(e: FormEvent) {
    e.preventDefault();
    setStatus("saving");
    const response = await fetch("/api/admin/module-prices", {
      method: "PUT",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        prices: Object.fromEntries(Object.entries(prices).map(([key, value]) => [key, Math.max(0, Number(value) || 0)])),
      }),
    });
    setStatus(response.ok ? "saved" : "error");
    if (response.ok) router.refresh();
  }

  return (
    <SectionCard title="Մոդուլների գները" description="Ամսական, դրամով։ 0՝ մոդուլը անվճար է։">
      <form onSubmit={submit} className="space-y-4">
        <TableWrap>
          <Thead>
            <Th>Մոդուլ</Th>
            <Th className="w-48">Գին ամսական, դր.</Th>
          </Thead>
          <tbody>
            {modules.map((module) => (
              <Tr key={module.key}>
                <Td>
                  <p className="font-medium text-ink">{module.label}</p>
                  <p className="text-xs text-ink-muted">{module.description}</p>
                </Td>
                <Td>
                  <Input
                    type="number"
                    min={0}
                    value={prices[module.key] ?? "0"}
                    onChange={(e) => setPrices({ ...prices, [module.key]: e.target.value })}
                  />
                </Td>
              </Tr>
            ))}
          </tbody>
        </TableWrap>
        <div className="flex items-center gap-3">
          <Button type="submit" disabled={status === "saving"}>
            Պահպանել գները
          </Button>
          <FormStatus status={status} />
        </div>
      </form>
    </SectionCard>
  );
}
