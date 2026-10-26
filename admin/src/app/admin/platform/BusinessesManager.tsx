"use client";

import { useEffect, useState } from "react";
import Link from "next/link";
import { usePathname, useRouter } from "next/navigation";
import type { ContactChannelType } from "@/lib/api";
import type { AdminTenantDto } from "@/lib/adminApi";
import { BusinessLogo } from "../_components/BusinessLogo";
import { TenantLoginsPanel } from "./TenantLoginsPanel";
import { TenantModulesPanel } from "./TenantModulesPanel";
import { Badge, Button, EmptyRow, FieldLabel, Input, Select, TableWrap, Td, Th, Thead, Tr, buttonClasses } from "@/ui";

const LOCALE_LABELS: Record<string, string> = { hy: "Հայերեն", ru: "Русский", en: "English" };

const CONTACT_LABELS: Record<ContactChannelType, string> = {
  phone: "Հեռ.",
  whatsapp: "WhatsApp",
  telegram: "Telegram",
  instagram: "Instagram",
  facebook: "Facebook",
  tiktok: "TikTok",
  viber: "Viber",
  email: "Email",
  other: "Այլ",
};

/** The address in the admin's language first, else whichever the business filled in. */
function addressOf(tenant: AdminTenantDto): string | null {
  const address = tenant.address ?? {};
  return address.hy || address.ru || address.en || null;
}

/** Search by name, slug, phone, any contact or address; the server matches, the URL keeps it (?q=). */
function SearchBox({ initial }: { initial: string }) {
  const router = useRouter();
  const pathname = usePathname();
  const [value, setValue] = useState(initial);

  useEffect(() => {
    if (value.trim() === initial) return;
    const timer = setTimeout(() => {
      const q = value.trim();
      router.replace(q ? `${pathname}?q=${encodeURIComponent(q)}` : pathname, { scroll: false });
    }, 300);
    return () => clearTimeout(timer);
  }, [value, initial, pathname, router]);

  return (
    <Input
      type="search"
      value={value}
      onChange={(e) => setValue(e.target.value)}
      placeholder="Որոնել՝ անվանում, հեռախոս, Instagram, հասցե…"
      className="max-w-md"
    />
  );
}

export function BusinessesManager({
  tenants,
  search,
  currentTenantId,
  isSuperAdmin,
}: {
  tenants: AdminTenantDto[];
  /** The current search (?q=); the list is already filtered by it. */
  search: string;
  currentTenantId: number | null;
  /** Modules and platform staff accounts are the super admin's; a platform moderator onboards only. */
  isSuperAdmin: boolean;
}) {
  const router = useRouter();
  const [switchingId, setSwitchingId] = useState<number | null>(null);
  // One panel under the table at a time: a business's logins or its modules.
  const [panel, setPanel] = useState<{ tenantId: number; kind: "logins" | "modules" } | null>(null);
  const panelTenant = tenants.find((tenant) => tenant.id === panel?.tenantId) ?? null;

  function togglePanel(tenantId: number, kind: "logins" | "modules") {
    setPanel((open) => (open?.tenantId === tenantId && open.kind === kind ? null : { tenantId, kind }));
  }

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
      <SearchBox initial={search} />

      <TableWrap>
        <Thead>
          <Th>Անվանում</Th>
          <Th>Կոնտակտներ</Th>
          <Th>Slug</Th>
          <Th>Կայքեր</Th>
          <Th>Մուտքեր</Th>
          <Th>Կարգավիճակ</Th>
          <Th />
        </Thead>
        <tbody>
          {tenants.map((tenant) => {
            const isCurrent = tenant.id === currentTenantId;
            return (
              <Tr key={tenant.id}>
                <Td className="font-medium text-ink">
                  <span className="flex items-center gap-3">
                    <BusinessLogo name={tenant.name} logo={tenant.logo} />
                    <span className="min-w-0">
                      <span className="flex items-center gap-2">
                        {tenant.name}
                        {isCurrent ? <Badge tone="primary">Ընթացիկ</Badge> : null}
                      </span>
                      {addressOf(tenant) ? (
                        <span className="block max-w-64 truncate text-xs font-normal text-ink-muted">
                          {addressOf(tenant)}
                        </span>
                      ) : null}
                    </span>
                  </span>
                </Td>
                <Td className="text-xs">
                  {tenant.contacts && tenant.contacts.length > 0 ? (
                    <ul className="space-y-0.5">
                      {tenant.contacts.slice(0, 3).map((contact, i) => (
                        <li key={`${contact.type}-${i}`} className="whitespace-nowrap">
                          <span className="text-ink-muted">{CONTACT_LABELS[contact.type] ?? contact.type}՝ </span>
                          {contact.value}
                        </li>
                      ))}
                      {tenant.contacts.length > 3 ? (
                        <li className="text-ink-muted">և ևս {tenant.contacts.length - 3}</li>
                      ) : null}
                    </ul>
                  ) : (
                    <span className="text-ink-faint">—</span>
                  )}
                </Td>
                <Td className="whitespace-nowrap font-mono text-xs text-ink-muted">{tenant.slug}</Td>
                <Td>{tenant.sitesCount ?? 0}</Td>
                <Td>{tenant.usersCount ?? 0}</Td>
                <Td>
                  {tenant.status === "active" ? (
                    <Badge tone="success">Ակտիվ</Badge>
                  ) : (
                    <Badge tone="warning">Կասեցված</Badge>
                  )}
                </Td>
                <Td className="whitespace-nowrap text-right">
                  <span className="inline-flex items-center justify-end gap-2">
                    <Link
                      href={`/admin/platform/support?tenant=${tenant.id}`}
                      className={buttonClasses("secondary", "sm")}
                    >
                      Զրույց
                      {tenant.unreadMessages ? (
                        <span className="rounded-full bg-primary px-1.5 py-0.5 text-[11px] font-semibold leading-none text-primary-foreground">
                          {tenant.unreadMessages}
                        </span>
                      ) : null}
                    </Link>
                    <Button
                      size="sm"
                      variant={panel?.tenantId === tenant.id && panel.kind === "logins" ? "primary" : "secondary"}
                      onClick={() => togglePanel(tenant.id, "logins")}
                    >
                      Մուտքեր
                    </Button>
                    {isSuperAdmin ? (
                      <Button
                        size="sm"
                        variant={panel?.tenantId === tenant.id && panel.kind === "modules" ? "primary" : "secondary"}
                        onClick={() => togglePanel(tenant.id, "modules")}
                      >
                        Մոդուլներ
                      </Button>
                    ) : null}
                    <Button
                      size="sm"
                      variant="secondary"
                      disabled={switchingId !== null}
                      onClick={() => (isCurrent ? router.push("/admin") : switchTo(tenant, "/admin"))}
                    >
                      Մտնել
                    </Button>
                  </span>
                </Td>
              </Tr>
            );
          })}
          {tenants.length === 0 ? (
            <EmptyRow colSpan={7}>{search ? "Ոչինչ չի գտնվել։" : "Բիզնեսներ դեռ չկան։"}</EmptyRow>
          ) : null}
        </tbody>
      </TableWrap>

      {panelTenant && panel?.kind === "logins" ? (
        <TenantLoginsPanel
          key={panelTenant.id}
          tenant={panelTenant}
          isSuperAdmin={isSuperAdmin}
          onClose={() => setPanel(null)}
        />
      ) : null}
      {panelTenant && panel?.kind === "modules" && isSuperAdmin ? (
        <TenantModulesPanel key={panelTenant.id} tenant={panelTenant} onClose={() => setPanel(null)} />
      ) : null}

      <NewBusinessForm onCreated={(tenant) => router.push(`/admin/platform/sites?tenant=${tenant.id}`)} />
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
        body: JSON.stringify({
          name: name.trim(),
          slug: slug.trim() || undefined,
          default_locale: locale,
        }),
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
      <form
        onSubmit={handleSubmit}
        className="flex flex-wrap items-end gap-3 rounded-lg border border-border bg-surface p-4"
      >
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
          <span>«{created.name}» բիզնեսը ստեղծված է։ Հաջորդ քայլը՝ միացնել նրա կայքը և ստանալ բանալին։</span>
          <Button size="sm" onClick={() => onCreated(created)}>
            Միացնել կայք
          </Button>
        </div>
      ) : null}
    </div>
  );
}
