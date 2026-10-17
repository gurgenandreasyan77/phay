"use client";

import { useState } from "react";
import { useRouter } from "next/navigation";
import type { AdminTenantDto } from "@/lib/adminApi";
import { Badge, Button, EmptyRow, FieldLabel, Input, Select, TableWrap, Td, Th, Thead, Tr } from "../_components/ui";

const LOCALE_LABELS: Record<string, string> = { hy: "Հայերեն", ru: "Русский", en: "English" };

export function BusinessesManager({
  tenants,
  currentTenantId,
}: {
  tenants: AdminTenantDto[];
  currentTenantId: number;
}) {
  const router = useRouter();
  const [switchingId, setSwitchingId] = useState<number | null>(null);

  async function switchTo(tenant: AdminTenantDto, then: string) {
    setSwitchingId(tenant.id);
    try {
      const response = await fetch("/api/admin/switch-tenant", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ tenant_id: tenant.id }),
      });
      if (response.ok) {
        router.push(then);
        router.refresh();
      }
    } finally {
      setSwitchingId(null);
    }
  }

  return (
    <div className="space-y-6">
      <TableWrap>
        <Thead>
          <Th>Անվանում</Th>
          <Th>Slug</Th>
          <Th>Լեզու</Th>
          <Th>Կայքեր</Th>
          <Th>Կարգավիճակ</Th>
          <Th />
        </Thead>
        <tbody>
          {tenants.map((tenant) => {
            const isCurrent = tenant.id === currentTenantId;
            return (
              <Tr key={tenant.id}>
                <Td className="font-medium text-ink">
                  <span className="flex items-center gap-2">
                    {tenant.name}
                    {isCurrent ? <Badge tone="primary">Ընթացիկ</Badge> : null}
                  </span>
                </Td>
                <Td className="font-mono text-xs text-ink-muted">{tenant.slug}</Td>
                <Td>{LOCALE_LABELS[tenant.defaultLocale] ?? tenant.defaultLocale}</Td>
                <Td>{tenant.sitesCount ?? 0}</Td>
                <Td>
                  {tenant.status === "active" ? (
                    <Badge tone="success">Ակտիվ</Badge>
                  ) : (
                    <Badge tone="warning">Կասեցված</Badge>
                  )}
                </Td>
                <Td className="text-right">
                  {isCurrent ? (
                    <Button size="sm" variant="secondary" onClick={() => router.push("/admin/sites")}>
                      Կայքեր
                    </Button>
                  ) : (
                    <Button
                      size="sm"
                      variant="secondary"
                      disabled={switchingId !== null}
                      onClick={() => switchTo(tenant, "/admin")}
                    >
                      Անցնել այս բիզնեսին
                    </Button>
                  )}
                </Td>
              </Tr>
            );
          })}
          {tenants.length === 0 ? <EmptyRow colSpan={6}>Բիզնեսներ դեռ չկան։</EmptyRow> : null}
        </tbody>
      </TableWrap>

      <NewBusinessForm onCreated={(tenant) => switchTo(tenant, "/admin/sites")} />
    </div>
  );
}

function NewBusinessForm({ onCreated }: { onCreated: (tenant: AdminTenantDto) => void }) {
  const router = useRouter();
  const [name, setName] = useState("");
  const [slug, setSlug] = useState("");
  const [locale, setLocale] = useState("hy");
  const [status, setStatus] = useState<"idle" | "saving" | "error" | "taken">("idle");
  const [created, setCreated] = useState<AdminTenantDto | null>(null);

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault();
    if (!name.trim()) return;
    setStatus("saving");

    try {
      const response = await fetch("/api/admin/tenants", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ name: name.trim(), slug: slug.trim() || undefined, default_locale: locale }),
      });

      if (!response.ok) {
        setStatus(response.status === 422 ? "taken" : "error");
        return;
      }

      const json = (await response.json()) as { data: AdminTenantDto };
      setCreated(json.data);
      setName("");
      setSlug("");
      setStatus("idle");
      router.refresh();
    } catch {
      setStatus("error");
    }
  }

  return (
    <div className="space-y-3">
      <form onSubmit={handleSubmit} className="flex flex-wrap items-end gap-3 rounded-lg border border-border bg-surface p-4">
        <div className="min-w-56 flex-1 space-y-1.5">
          <FieldLabel>Բիզնեսի անվանում</FieldLabel>
          <Input value={name} onChange={(e) => setName(e.target.value)} placeholder="Օրինակ՝ Test Shop" required />
        </div>
        <div className="space-y-1.5">
          <FieldLabel>Slug (ոչ պարտադիր)</FieldLabel>
          <Input value={slug} onChange={(e) => setSlug(e.target.value)} placeholder="test-shop" />
        </div>
        <div className="space-y-1.5">
          <FieldLabel>Հիմնական լեզու</FieldLabel>
          <Select value={locale} onChange={(e) => setLocale(e.target.value)}>
            {Object.entries(LOCALE_LABELS).map(([value, label]) => (
              <option key={value} value={value}>
                {label}
              </option>
            ))}
          </Select>
        </div>
        <Button type="submit" disabled={status === "saving"}>
          Ստեղծել բիզնես
        </Button>
        {status === "taken" ? (
          <span className="text-sm text-danger">Այս slug-ով բիզնես արդեն կա կամ դաշտերը սխալ են։</span>
        ) : null}
        {status === "error" ? <span className="text-sm text-danger">Չհաջողվեց ստեղծել բիզնեսը։</span> : null}
      </form>

      {created ? (
        <div className="flex flex-wrap items-center gap-3 rounded-lg border border-success/30 bg-success-light px-4 py-3 text-sm text-ink">
          <span>
            «{created.name}» բիզնեսը ստեղծված է։ Հաջորդ քայլը՝ միացնել նրա կայքը և ստանալ բանալին։
          </span>
          <Button size="sm" onClick={() => onCreated(created)}>
            Անցնել և միացնել կայք
          </Button>
        </div>
      ) : null}
    </div>
  );
}
