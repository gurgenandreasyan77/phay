"use client";

import { useCallback, useEffect, useState } from "react";
import { useRouter } from "next/navigation";
import type { AdminTenantDto, AdminTenantUserDto } from "@/lib/adminApi";
import {
  Badge,
  Button,
  EmptyRow,
  FieldLabel,
  Input,
  SectionCard,
  TableWrap,
  Td,
  Th,
  Thead,
  Tr,
} from "../_components/ui";

/** 12 characters without look-alikes (0/O, 1/l/I), easy to dictate on the phone. */
function generatePassword(): string {
  const alphabet = "abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789";
  const bytes = crypto.getRandomValues(new Uint32Array(12));
  return Array.from(bytes, (n) => alphabet[n % alphabet.length]).join("");
}

type Credentials = { email: string; password: string };

async function fetchLogins(tenantId: number): Promise<AdminTenantUserDto[] | null> {
  const response = await fetch(`/api/admin/tenants/${tenantId}/users`, { cache: "no-store" });
  if (!response.ok) return null;
  const json = (await response.json()) as { data: AdminTenantUserDto[] };
  return json.data;
}

/**
 * Logins of one business. The platform administrator creates them by hand
 * while onboarding a client and hands the email + password over; the owner
 * then signs in to this same admin panel and sees only their business.
 */
export function TenantLoginsPanel({ tenant, onClose }: { tenant: AdminTenantDto; onClose: () => void }) {
  const router = useRouter();
  const [users, setUsers] = useState<AdminTenantUserDto[] | null>(null);
  const [loadError, setLoadError] = useState(false);
  const [credentials, setCredentials] = useState<Credentials | null>(null);

  const load = useCallback(async () => {
    const data = await fetchLogins(tenant.id);
    setLoadError(data === null);
    if (data) setUsers(data);
  }, [tenant.id]);

  // Remounted per business (key={tenant.id}), so there's no state to reset here.
  useEffect(() => {
    let cancelled = false;
    fetchLogins(tenant.id).then((data) => {
      if (cancelled) return;
      setLoadError(data === null);
      if (data) setUsers(data);
    });
    return () => {
      cancelled = true;
    };
  }, [tenant.id]);

  async function changed(next?: Credentials) {
    setCredentials(next ?? null);
    await load();
    router.refresh(); // the logins count in the businesses table
  }

  return (
    <SectionCard
      title={`«${tenant.name}» բիզնեսի մուտքերը`}
      description="Այս էլ. փոստով և գաղտնաբառով բիզնեսի տերը մուտք է գործում նույն ադմին վահանակ և տեսնում է միայն իր բիզնեսը՝ պատվերներ, ծառայություններ, կարգավորումներ։"
      actions={
        <Button size="sm" variant="ghost" onClick={onClose}>
          Փակել
        </Button>
      }
    >
      <div className="space-y-4">
        {credentials ? <CredentialsNotice credentials={credentials} onDismiss={() => setCredentials(null)} /> : null}

        <TableWrap>
          <Thead>
            <Th>Անուն</Th>
            <Th>Էլ. փոստ</Th>
            <Th>Դեր</Th>
            <Th />
          </Thead>
          <tbody>
            {(users ?? []).map((user) => (
              <LoginRow key={user.id} tenant={tenant} user={user} onChanged={changed} />
            ))}
            {users && users.length === 0 ? <EmptyRow colSpan={4}>Մուտքեր դեռ չկան։</EmptyRow> : null}
            {!users && !loadError ? <EmptyRow colSpan={4}>Բեռնվում է…</EmptyRow> : null}
            {loadError ? <EmptyRow colSpan={4}>Չհաջողվեց բեռնել մուտքերը։</EmptyRow> : null}
          </tbody>
        </TableWrap>

        <NewLoginForm tenant={tenant} onCreated={changed} />
      </div>
    </SectionCard>
  );
}

function CredentialsNotice({ credentials, onDismiss }: { credentials: Credentials; onDismiss: () => void }) {
  const [copied, setCopied] = useState(false);
  const text = `Մուտք՝ ${typeof window !== "undefined" ? window.location.origin : ""}/admin/login\nԷլ. փոստ՝ ${credentials.email}\nԳաղտնաբառ՝ ${credentials.password}`;

  async function copy() {
    await navigator.clipboard.writeText(text);
    setCopied(true);
  }

  return (
    <div className="space-y-2 rounded-lg border border-success/30 bg-success-light px-4 py-3 text-sm text-ink">
      <p className="font-medium">Ուղարկեք այս տվյալները հաճախորդին։ Գաղտնաբառը այլևս չի ցուցադրվի։</p>
      <pre className="whitespace-pre-wrap rounded-md bg-surface px-3 py-2 font-mono text-xs">{text}</pre>
      <div className="flex gap-2">
        <Button size="sm" onClick={copy}>
          {copied ? "Պատճենված է" : "Պատճենել"}
        </Button>
        <Button size="sm" variant="ghost" onClick={onDismiss}>
          Թաքցնել
        </Button>
      </div>
    </div>
  );
}

function LoginRow({
  tenant,
  user,
  onChanged,
}: {
  tenant: AdminTenantDto;
  user: AdminTenantUserDto;
  onChanged: (credentials?: Credentials) => Promise<void>;
}) {
  const [pending, setPending] = useState(false);
  const [error, setError] = useState<string | null>(null);

  async function resetPassword() {
    if (!confirm(`Նոր գաղտնաբառ ստեղծե՞լ ${user.email}-ի համար։ Հինը այլևս չի աշխատի։`)) return;
    const password = generatePassword();
    setPending(true);
    setError(null);
    try {
      const response = await fetch(`/api/admin/tenants/${tenant.id}/users/${user.id}`, {
        method: "PATCH",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ password }),
      });
      if (!response.ok) {
        setError("Չհաջողվեց փոխել գաղտնաբառը։");
        return;
      }
      await onChanged({ email: user.email, password });
    } finally {
      setPending(false);
    }
  }

  async function remove() {
    const question = user.isSuperAdmin
      ? `Հեռացնե՞լ ${user.email}-ը այս բիզնեսից։ Պլատֆորմի ադմինի հաշիվը կմնա։`
      : `Ջնջե՞լ ${user.email} մուտքը։ Նա այլևս չի կարողանա մուտք գործել։`;
    if (!confirm(question)) return;
    setPending(true);
    setError(null);
    try {
      const response = await fetch(`/api/admin/tenants/${tenant.id}/users/${user.id}`, { method: "DELETE" });
      if (!response.ok) {
        setError(response.status === 422 ? "Չեք կարող հեռացնել ինքներդ ձեզ։" : "Չհաջողվեց հեռացնել։");
        return;
      }
      await onChanged();
    } finally {
      setPending(false);
    }
  }

  return (
    <Tr>
      <Td className="font-medium text-ink">{user.name}</Td>
      <Td className="text-ink-muted">{user.email}</Td>
      <Td>{user.isSuperAdmin ? <Badge tone="primary">Պլատֆորմի ադմին</Badge> : <Badge>Բիզնեսի տեր</Badge>}</Td>
      <Td className="text-right">
        <span className="inline-flex flex-wrap items-center justify-end gap-2">
          {error ? <span className="text-xs text-danger">{error}</span> : null}
          {!user.isSuperAdmin ? (
            <Button size="sm" variant="secondary" disabled={pending} onClick={resetPassword}>
              Նոր գաղտնաբառ
            </Button>
          ) : null}
          <Button size="sm" variant="secondary" disabled={pending} onClick={remove}>
            Հեռացնել
          </Button>
        </span>
      </Td>
    </Tr>
  );
}

function NewLoginForm({
  tenant,
  onCreated,
}: {
  tenant: AdminTenantDto;
  onCreated: (credentials: Credentials) => Promise<void>;
}) {
  const [name, setName] = useState("");
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState(generatePassword);
  const [status, setStatus] = useState<"idle" | "saving" | "error">("idle");
  const [errors, setErrors] = useState<Record<string, string[]>>({});

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault();
    setStatus("saving");
    setErrors({});
    try {
      const response = await fetch(`/api/admin/tenants/${tenant.id}/users`, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          name: name.trim(),
          email: email.trim().toLowerCase(),
          password,
        }),
      });
      if (!response.ok) {
        if (response.status === 422) {
          const json = (await response.json().catch(() => null)) as {
            errors?: Record<string, string[]>;
          } | null;
          setErrors(json?.errors ?? { email: ["invalid"] });
        }
        setStatus("error");
        return;
      }
      const created = { email: email.trim().toLowerCase(), password };
      setName("");
      setEmail("");
      setPassword(generatePassword());
      setStatus("idle");
      await onCreated(created);
    } catch {
      setStatus("error");
    }
  }

  return (
    <form
      onSubmit={handleSubmit}
      className="flex flex-wrap items-end gap-3 rounded-lg border border-border bg-surface p-4"
    >
      <div className="min-w-44 flex-1 space-y-1.5">
        <FieldLabel>Անուն</FieldLabel>
        <Input value={name} onChange={(e) => setName(e.target.value)} placeholder="Օրինակ՝ Արամ" required />
      </div>
      <div className="min-w-56 flex-1 space-y-1.5">
        <FieldLabel>Էլ. փոստ</FieldLabel>
        <Input
          type="email"
          value={email}
          onChange={(e) => setEmail(e.target.value)}
          placeholder="owner@example.am"
          required
        />
      </div>
      <div className="space-y-1.5">
        <FieldLabel>Գաղտնաբառ (առնվազն 8 նիշ)</FieldLabel>
        <div className="flex gap-2">
          <Input
            value={password}
            onChange={(e) => setPassword(e.target.value)}
            minLength={8}
            required
            className="font-mono"
          />
          <Button type="button" size="md" variant="secondary" onClick={() => setPassword(generatePassword())}>
            Նոր
          </Button>
        </div>
      </div>
      <Button type="submit" disabled={status === "saving"}>
        Ավելացնել մուտք
      </Button>
      {status === "error" ? (
        <p className="basis-full text-sm text-danger">
          {errors.email
            ? "Այս էլ. փոստով մուտք արդեն կա կամ հասցեն սխալ է։"
            : errors.password
              ? "Գաղտնաբառը պետք է լինի առնվազն 8 նիշ։"
              : "Չհաջողվեց ավելացնել մուտքը։"}
        </p>
      ) : null}
    </form>
  );
}
