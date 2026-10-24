"use client";

import { useCallback, useEffect, useState } from "react";
import { useRouter } from "next/navigation";
import type { AccountLinkDto, AdminTenantDto, AdminTenantUserDto, BusinessRole } from "@/lib/adminApi";
import { BUSINESS_ROLE_HINTS, BUSINESS_ROLE_LABELS, BUSINESS_ROLES, PLATFORM_ROLE_LABELS } from "@/lib/roles";
import {
  Badge,
  Button,
  EmptyRow,
  FieldLabel,
  Input,
  SectionCard,
  Select,
  TableWrap,
  Td,
  Th,
  Thead,
  Tr,
} from "../_components/ui";
import { AccountActions, AccountBadges, CopyLoginButton, LinkNotice, type AccountLink } from "./credentials";

async function fetchLogins(tenantId: number): Promise<AdminTenantUserDto[] | null> {
  const response = await fetch(`/api/admin/tenants/${tenantId}/users`, { cache: "no-store" });
  if (!response.ok) return null;
  const json = (await response.json()) as { data: AdminTenantUserDto[] };
  return json.data;
}

/**
 * Logins of one business. Platform staff create them while onboarding a
 * client, each with a role (owner, admin, moderator); the person gets an
 * invitation link (by email, and to pass on by messenger), sets their own
 * password, then signs in to this same admin panel and sees only their
 * business, as far as their role allows.
 */
export function TenantLoginsPanel({
  tenant,
  isSuperAdmin,
  onClose,
}: {
  tenant: AdminTenantDto;
  /** Only the super admin may change accounts of platform staff. */
  isSuperAdmin: boolean;
  onClose: () => void;
}) {
  const router = useRouter();
  const [users, setUsers] = useState<AdminTenantUserDto[] | null>(null);
  const [loadError, setLoadError] = useState(false);
  const [link, setLink] = useState<AccountLink | null>(null);

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

  async function changed(next?: AccountLink) {
    // A plain change (role, removal) keeps the last link on screen.
    if (next) setLink(next);
    await load();
    router.refresh(); // the logins count in the businesses table
  }

  return (
    <SectionCard
      title={`«${tenant.name}» բիզնեսի մուտքերը`}
      description="Յուրաքանչյուր աշխատակից մուտք է գործում իր էլ. փոստով (մուտքանուն) և գաղտնաբառով նույն ադմին վահանակ և տեսնում է միայն այս բիզնեսը՝ իր դերի թույլատրած բաժինները։ Գաղտնաբառը ստեղծում է ինքը՝ հրավերի հղումով. մենք այն չենք տեսնում։ Մոռացված գաղտնաբառի համար ուղարկեք վերականգնման հղում։"
      actions={
        <Button size="sm" variant="ghost" onClick={onClose}>
          Փակել
        </Button>
      }
    >
      <div className="space-y-4">
        {link ? <LinkNotice link={link} onDismiss={() => setLink(null)} /> : null}

        <TableWrap>
          <Thead>
            <Th>Անուն</Th>
            <Th>Մուտքանուն (էլ. փոստ)</Th>
            <Th>Դեր</Th>
            <Th />
          </Thead>
          <tbody>
            {(users ?? []).map((user) => (
              <LoginRow key={user.id} tenant={tenant} user={user} isSuperAdmin={isSuperAdmin} onChanged={changed} />
            ))}
            {users && users.length === 0 ? <EmptyRow colSpan={4}>Մուտքեր դեռ չկան։</EmptyRow> : null}
            {!users && !loadError ? <EmptyRow colSpan={4}>Բեռնվում է…</EmptyRow> : null}
            {loadError ? <EmptyRow colSpan={4}>Չհաջողվեց բեռնել մուտքերը։</EmptyRow> : null}
          </tbody>
        </TableWrap>

        <RolesLegend />

        <NewLoginForm tenant={tenant} onCreated={changed} />
      </div>
    </SectionCard>
  );
}

function RolesLegend() {
  return (
    <ul className="space-y-1 text-xs text-ink-muted">
      {BUSINESS_ROLES.map((role) => (
        <li key={role}>
          <span className="font-medium text-ink">{BUSINESS_ROLE_LABELS[role]}</span>՝ {BUSINESS_ROLE_HINTS[role]}
        </li>
      ))}
    </ul>
  );
}

function LoginRow({
  tenant,
  user,
  isSuperAdmin,
  onChanged,
}: {
  tenant: AdminTenantDto;
  user: AdminTenantUserDto;
  isSuperAdmin: boolean;
  onChanged: (link?: AccountLink) => Promise<void>;
}) {
  const [pending, setPending] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const staff = Boolean(user.platformRole ?? (user.isSuperAdmin ? "super_admin" : null));
  // Staff accounts are managed on the platform team page.
  const editable = !staff;
  const removable = !staff || isSuperAdmin;

  async function patch(body: Record<string, string>): Promise<boolean> {
    const response = await fetch(`/api/admin/tenants/${tenant.id}/users/${user.id}`, {
      method: "PATCH",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(body),
    });
    return response.ok;
  }

  async function changeRole(role: BusinessRole) {
    setPending(true);
    setError(null);
    try {
      if (!(await patch({ role }))) {
        setError("Չհաջողվեց փոխել դերը։");
        return;
      }
      await onChanged();
    } finally {
      setPending(false);
    }
  }

  async function remove() {
    const question = staff
      ? `Հեռացնե՞լ ${user.email}-ը այս բիզնեսից։ Պլատֆորմի հաշիվը կմնա։`
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
      <Td className="font-medium text-ink">
        <span className="flex flex-col gap-1">
          {user.name}
          <AccountBadges user={user} />
        </span>
      </Td>
      <Td className="text-ink-muted">
        <span className="flex flex-wrap items-center gap-2">
          <span className="font-mono text-xs">{user.email}</span>
          <CopyLoginButton email={user.email} />
        </span>
      </Td>
      <Td>
        {staff ? (
          <Badge tone="primary">{PLATFORM_ROLE_LABELS[user.platformRole ?? "super_admin"]}</Badge>
        ) : (
          <Select
            value={user.role ?? "owner"}
            disabled={pending}
            onChange={(e) => changeRole(e.target.value as BusinessRole)}
            aria-label="Դեր"
          >
            {BUSINESS_ROLES.map((role) => (
              <option key={role} value={role}>
                {BUSINESS_ROLE_LABELS[role]}
              </option>
            ))}
          </Select>
        )}
      </Td>
      <Td className="text-right">
        <span className="inline-flex flex-wrap items-center justify-end gap-2">
          {error ? <span className="text-xs text-danger">{error}</span> : null}
          {editable ? (
            <AccountActions
              user={user}
              base={`/api/admin/tenants/${tenant.id}/users/${user.id}`}
              disabled={pending}
              onLink={(link) => onChanged({ ...link, role: user.role ? BUSINESS_ROLE_LABELS[user.role] : undefined })}
              onChanged={() => onChanged()}
              onError={setError}
            />
          ) : null}
          {removable ? (
            <Button size="sm" variant="secondary" disabled={pending} onClick={remove}>
              Հեռացնել
            </Button>
          ) : null}
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
  onCreated: (link: AccountLink) => Promise<void>;
}) {
  const [name, setName] = useState("");
  const [email, setEmail] = useState("");
  const [role, setRole] = useState<BusinessRole>("owner");
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
          role,
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
      const json = (await response.json().catch(() => null)) as { invitation?: AccountLinkDto } | null;
      const created: AccountLink = {
        url: json?.invitation?.url ?? "",
        expiresAt: json?.invitation?.expiresAt ?? "",
        emailed: json?.invitation?.emailed ?? false,
        kind: "invitation",
        name: name.trim(),
        email: email.trim().toLowerCase(),
        role: BUSINESS_ROLE_LABELS[role],
      };
      setName("");
      setEmail("");
      setRole("owner");
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
        <FieldLabel>Էլ. փոստ (մուտքանուն)</FieldLabel>
        <Input
          type="email"
          value={email}
          onChange={(e) => setEmail(e.target.value)}
          placeholder="owner@example.am"
          required
        />
      </div>
      <div className="space-y-1.5">
        <FieldLabel>Դեր</FieldLabel>
        <Select value={role} onChange={(e) => setRole(e.target.value as BusinessRole)}>
          {BUSINESS_ROLES.map((r) => (
            <option key={r} value={r}>
              {BUSINESS_ROLE_LABELS[r]}
            </option>
          ))}
        </Select>
      </div>
      <Button type="submit" disabled={status === "saving"}>
        Ավելացնել և ուղարկել հրավեր
      </Button>
      {status === "error" ? (
        <p className="basis-full text-sm text-danger">
          {errors.email
            ? "Այս էլ. փոստով մուտք արդեն կա կամ հասցեն սխալ է։"
            : "Չհաջողվեց ավելացնել մուտքը։"}
        </p>
      ) : null}
    </form>
  );
}
