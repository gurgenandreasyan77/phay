"use client";

import { useState } from "react";
import { useRouter } from "next/navigation";
import { Check, Copy, KeyRound, RefreshCw } from "lucide-react";
import type { AdminSiteDto, AdminTenantDto } from "@/lib/adminApi";
import { Badge, Button, Card, EmptyCard, FieldLabel, FormStatus, Input, Select, Textarea } from "@/ui";

export function SitesManager({
  sites,
  tenants,
  initialTenantId,
}: {
  sites: AdminSiteDto[];
  tenants: AdminTenantDto[];
  /** Preselected in the new-site form (e.g. right after creating a business). */
  initialTenantId: number | null;
}) {
  const router = useRouter();

  return (
    <div className="space-y-6">
      <NewSiteForm tenants={tenants} initialTenantId={initialTenantId} onCreated={() => router.refresh()} />

      {sites.length === 0 ? (
        <EmptyCard>Դեռ ոչ մի կայք միացված չէ։</EmptyCard>
      ) : (
        sites.map((site) => <SiteCard key={site.id} site={site} onChanged={() => router.refresh()} />)
      )}
    </div>
  );
}

function originsToText(origins: string[]): string {
  return origins.join("\n");
}

function textToOrigins(text: string): string[] {
  return text
    .split(/[\s,]+/)
    .map((o) => o.trim().replace(/\/+$/, ""))
    .filter(Boolean);
}

function SiteCard({ site, onChanged }: { site: AdminSiteDto; onChanged: () => void }) {
  const [name, setName] = useState(site.name);
  const [domain, setDomain] = useState(site.domain ?? "");
  const [origins, setOrigins] = useState(originsToText(site.allowedOrigins));
  const [isActive, setIsActive] = useState(site.isActive);
  const [revalidateUrl, setRevalidateUrl] = useState(site.revalidateUrl ?? "");
  const [revalidateSecret, setRevalidateSecret] = useState("");
  const [status, setStatus] = useState<"idle" | "saving" | "saved" | "error">("idle");
  const [rotating, setRotating] = useState(false);

  const dirty =
    name !== site.name ||
    domain !== (site.domain ?? "") ||
    origins !== originsToText(site.allowedOrigins) ||
    revalidateUrl !== (site.revalidateUrl ?? "") ||
    revalidateSecret !== "" ||
    isActive !== site.isActive;

  async function save() {
    setStatus("saving");
    try {
      const response = await fetch(`/api/admin/sites/${site.id}`, {
        method: "PATCH",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          name: name.trim(),
          domain: domain.trim() || null,
          allowed_origins: textToOrigins(origins),
          revalidate_url: revalidateUrl.trim() || null,
          // Empty keeps the secret already set.
          revalidate_secret: revalidateSecret,
          is_active: isActive,
        }),
      });
      setStatus(response.ok ? "saved" : "error");
      if (response.ok) {
        setRevalidateSecret("");
        // Show the origins the way the API stored them (no trailing slash,
        // no duplicates), so the form isn't "dirty" right after saving.
        setOrigins(originsToText([...new Set(textToOrigins(origins))]));
        onChanged();
      }
    } catch {
      setStatus("error");
    }
  }

  async function rotateKey() {
    if (
      !confirm(
        "Ստեղծե՞լ նոր բանալի։ Հին բանալին անմիջապես կդադարի աշխատել, և կայքը տվյալներ չի ստանա, մինչև նրա կարգավորումներում չթարմացնեք բանալին։",
      )
    ) {
      return;
    }
    setRotating(true);
    try {
      await fetch(`/api/admin/sites/${site.id}/rotate-key`, { method: "POST" });
      onChanged();
    } finally {
      setRotating(false);
    }
  }

  return (
    <Card className="space-y-5">
      <div className="flex flex-wrap items-center gap-3">
        <h2 className="text-base font-semibold text-ink">{site.name}</h2>
        {site.tenant ? <Badge tone="primary">{site.tenant.name}</Badge> : null}
        {site.isActive ? <Badge tone="success">Ակտիվ</Badge> : <Badge tone="warning">Անջատված</Badge>}
        {site.domain ? <span className="text-sm text-ink-muted">{site.domain}</span> : null}
      </div>

      <div className="space-y-2">
        <FieldLabel>Կայքի բանալի (X-Site-Key)</FieldLabel>
        <div className="flex flex-wrap items-center gap-2">
          <code className="min-w-0 flex-1 truncate rounded-md border border-border bg-surface-secondary px-3 py-2 font-mono text-sm text-ink">
            {site.publicKey}
          </code>
          <CopyButton value={site.publicKey} />
          <Button variant="secondary" size="sm" onClick={rotateKey} disabled={rotating}>
            <KeyRound className="h-3.5 w-3.5" strokeWidth={1.75} />
            Նոր բանալի
          </Button>
        </div>
        <p className="text-xs text-ink-muted">
          Բանալին գաղտնի չէ․ այն երևում է կայքի կոդում։ Կայքը պաշտպանում են թույլատրված հասցեները ներքևում։
        </p>
      </div>

      <ConnectInstructions siteKey={site.publicKey} />

      <div className="grid gap-4 md:grid-cols-2">
        <div className="space-y-1.5">
          <FieldLabel>Անվանում</FieldLabel>
          <Input value={name} onChange={(e) => setName(e.target.value)} disabled={status === "saving"} />
        </div>
        <div className="space-y-1.5">
          <FieldLabel>Դոմեն</FieldLabel>
          <Input
            value={domain}
            onChange={(e) => setDomain(e.target.value)}
            placeholder="shop.am"
            disabled={status === "saving"}
          />
        </div>
        <div className="space-y-1.5 md:col-span-2">
          <FieldLabel>Թույլատրված հասցեներ (origin)</FieldLabel>
          <Textarea
            rows={2}
            value={origins}
            onChange={(e) => setOrigins(e.target.value)}
            placeholder={"https://shop.am\nhttps://www.shop.am"}
            disabled={status === "saving"}
          />
          <p className="text-xs text-ink-muted">
            Մեկ տողում մեկ հասցե։ Միայն այս հասցեներից բացված էջերը կարող են օգտագործել բանալին բրաուզերում
            (օրինակ՝ հայտի ձևի համար)։ Դատարկ թողնելու դեպքում թույլատրվում են դոմենը և նրա www տարբերակը։
          </p>
        </div>
        <div className="space-y-1.5">
          <FieldLabel>Քեշի թարմացման հասցե (webhook)</FieldLabel>
          <Input
            value={revalidateUrl}
            onChange={(e) => setRevalidateUrl(e.target.value)}
            placeholder="https://shop.am/api/revalidate"
            disabled={status === "saving"}
          />
        </div>
        <div className="space-y-1.5">
          <FieldLabel>Webhook-ի գաղտնաբառ</FieldLabel>
          <Input
            type="password"
            autoComplete="new-password"
            value={revalidateSecret}
            onChange={(e) => setRevalidateSecret(e.target.value)}
            placeholder={site.hasRevalidateSecret ? "Նշված է․ դատարկ թողեք՝ չփոխելու համար" : "Նվազագույնը 16 նիշ"}
            disabled={status === "saving"}
          />
        </div>
        <p className="text-xs text-ink-muted md:col-span-2">
          Ադմինում փոփոխությունից հետո պլատֆորմը այս հասցեին ուղարկում է թարմացվող բաժինները, և կայքը միանգամից
          ցույց է տալիս նոր տվյալները։ Գաղտնաբառը պետք է համընկնի կայքի <code className="font-mono">REVALIDATE_SECRET</code>-ի հետ։
        </p>
        <RevalidationCheck siteId={site.id} enabled={Boolean(site.revalidateUrl && site.hasRevalidateSecret)} />
      </div>

      <div className="flex flex-wrap items-center gap-4">
        <label className="flex items-center gap-2 text-sm text-ink">
          <input
            type="checkbox"
            checked={isActive}
            onChange={(e) => setIsActive(e.target.checked)}
            disabled={status === "saving"}
            className="h-4 w-4 accent-primary"
          />
          Կայքը ակտիվ է
        </label>
        <Button size="sm" onClick={save} disabled={!dirty || status === "saving"}>
          Պահպանել
        </Button>
        <FormStatus status={status} errorLabel="Չհաջողվեց պահպանել։ Ստուգեք դոմենը և հասցեները։" />
      </div>
    </Card>
  );
}

function RevalidationCheck({ siteId, enabled }: { siteId: number; enabled: boolean }) {
  const [state, setState] = useState<"idle" | "checking" | "ok" | "failed">("idle");
  const [detail, setDetail] = useState("");

  async function check() {
    setState("checking");
    try {
      const response = await fetch(`/api/admin/sites/${siteId}/check-revalidation`, { method: "POST" });
      const body = (await response.json().catch(() => null)) as {
        data?: { ok: boolean; status: number | null; error: string | null };
      } | null;
      const result = body?.data;
      setState(result?.ok ? "ok" : "failed");
      setDetail(result ? (result.status ? `HTTP ${result.status}` : (result.error ?? "")) : "");
    } catch {
      setState("failed");
      setDetail("");
    }
  }

  return (
    <div className="flex flex-wrap items-center gap-3 md:col-span-2">
      <Button variant="secondary" size="sm" onClick={check} disabled={!enabled || state === "checking"}>
        <RefreshCw className="h-3.5 w-3.5" strokeWidth={1.75} />
        Ստուգել webhook-ը
      </Button>
      {!enabled ? <span className="text-xs text-ink-muted">Նախ պահպանեք հասցեն և գաղտնաբառը։</span> : null}
      {state === "ok" ? <span className="text-sm text-success">Կայքը ընդունեց թարմացումը։</span> : null}
      {state === "failed" ? (
        <span className="text-sm text-danger">Կայքը չընդունեց թարմացումը{detail ? ` (${detail})` : ""}։</span>
      ) : null}
    </div>
  );
}

function ConnectInstructions({ siteKey }: { siteKey: string }) {
  const envLine = `NEXT_PUBLIC_SITE_KEY=${siteKey}`;

  return (
    <details className="rounded-md border border-border bg-surface-secondary/60 px-4 py-3 text-sm">
      <summary className="cursor-pointer font-medium text-ink">Ինչպես միացնել կայքը</summary>
      <div className="mt-3 space-y-3 text-ink-muted">
        <p>
          <span className="font-medium text-ink">Next.js կայք։</span> Ավելացրեք այս տողը կայքի{" "}
          <code className="font-mono">.env</code> ֆայլում և վերագործարկեք կայքը (production-ում՝ նորից build արեք)։
        </p>
        <div className="flex items-center gap-2">
          <code className="min-w-0 flex-1 truncate rounded-md border border-border bg-surface px-3 py-2 font-mono text-xs text-ink">
            {envLine}
          </code>
          <CopyButton value={envLine} />
        </div>
        <p>
          <span className="font-medium text-ink">Այլ կայք։</span> API-ի յուրաքանչյուր հարցման մեջ ուղարկեք{" "}
          <code className="font-mono">X-Site-Key</code> header-ը այս բանալիով։
        </p>
      </div>
    </details>
  );
}

function CopyButton({ value }: { value: string }) {
  const [copied, setCopied] = useState(false);

  async function copy() {
    try {
      await navigator.clipboard.writeText(value);
      setCopied(true);
      setTimeout(() => setCopied(false), 1500);
    } catch {
      setCopied(false);
    }
  }

  return (
    <Button variant="secondary" size="sm" onClick={copy} aria-live="polite">
      {copied ? <Check className="h-3.5 w-3.5 text-success" strokeWidth={2} /> : <Copy className="h-3.5 w-3.5" strokeWidth={1.75} />}
      {copied ? "Պատճենված է" : "Պատճենել"}
    </Button>
  );
}

function NewSiteForm({
  tenants,
  initialTenantId,
  onCreated,
}: {
  tenants: AdminTenantDto[];
  initialTenantId: number | null;
  onCreated: () => void;
}) {
  const [tenantId, setTenantId] = useState(String(initialTenantId ?? tenants[0]?.id ?? ""));
  const [name, setName] = useState("");
  const [domain, setDomain] = useState("");
  const [status, setStatus] = useState<"idle" | "saving" | "error">("idle");

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault();
    if (!name.trim()) return;
    setStatus("saving");

    try {
      const response = await fetch("/api/admin/sites", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ tenant_id: Number(tenantId), name: name.trim(), domain: domain.trim() || null }),
      });

      if (!response.ok) {
        setStatus("error");
        return;
      }

      setName("");
      setDomain("");
      setStatus("idle");
      onCreated();
    } catch {
      setStatus("error");
    }
  }

  return (
    <form onSubmit={handleSubmit} className="flex flex-wrap items-end gap-3 rounded-lg border border-border bg-surface p-4">
      <div className="space-y-1.5">
        <FieldLabel>Բիզնես</FieldLabel>
        <Select value={tenantId} onChange={(e) => setTenantId(e.target.value)} required>
          {tenants.map((tenant) => (
            <option key={tenant.id} value={tenant.id}>
              {tenant.name}
            </option>
          ))}
        </Select>
      </div>
      <div className="min-w-56 flex-1 space-y-1.5">
        <FieldLabel>Նոր կայքի անվանում</FieldLabel>
        <Input value={name} onChange={(e) => setName(e.target.value)} placeholder="Օրինակ՝ shop.am" required />
      </div>
      <div className="space-y-1.5">
        <FieldLabel>Դոմեն (ոչ պարտադիր)</FieldLabel>
        <Input value={domain} onChange={(e) => setDomain(e.target.value)} placeholder="shop.am" />
      </div>
      <Button type="submit" disabled={status === "saving"}>
        Միացնել կայք
      </Button>
      {status === "error" ? <span className="text-sm text-danger">Չհաջողվեց ավելացնել։ Ստուգեք դոմենը։</span> : null}
    </form>
  );
}
