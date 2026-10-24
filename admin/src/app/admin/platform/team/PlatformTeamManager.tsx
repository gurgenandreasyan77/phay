"use client";

import { useState } from "react";
import { useRouter } from "next/navigation";
import type { AccountLinkDto, AdminPlatformUserDto, PlatformRole } from "@/lib/adminApi";
import { PLATFORM_ROLE_HINTS, PLATFORM_ROLE_LABELS, PLATFORM_ROLES } from "@/lib/roles";
import { Badge, Button, EmptyRow, FieldLabel, Input, Select, TableWrap, Td, Th, Thead, Tr } from "@/ui";
import { AccountActions, AccountBadges, CopyLoginButton, LinkNotice, type AccountLink } from "../credentials";

/**
 * Phay's own people: super admins and platform moderators (super admin
 * only). Each gets an invitation link, sets their own password and must set
 * up 2FA before the platform opens.
 */
export function PlatformTeamManager({ users, meId }: { users: AdminPlatformUserDto[]; meId: number }) {
  const router = useRouter();
  const [link, setLink] = useState<AccountLink | null>(null);

  function changed(next?: AccountLink) {
    if (next) setLink(next);
    router.refresh();
  }

  return (
    <div className="space-y-6">
      {link ? <LinkNotice link={link} onDismiss={() => setLink(null)} /> : null}

      <TableWrap>
        <Thead>
          <Th>Անուն</Th>
          <Th>Մուտքանուն (էլ. փոստ)</Th>
          <Th>Դեր</Th>
          <Th />
        </Thead>
        <tbody>
          {users.map((user) => (
            <TeamRow key={user.id} user={user} isMe={user.id === meId} onChanged={changed} />
          ))}
          {users.length === 0 ? <EmptyRow colSpan={4}>Թիմում դեռ ոչ ոք չկա։</EmptyRow> : null}
        </tbody>
      </TableWrap>

      <ul className="space-y-1 text-xs text-ink-muted">
        {PLATFORM_ROLES.map((role) => (
          <li key={role}>
            <span className="font-medium text-ink">{PLATFORM_ROLE_LABELS[role]}</span>՝ {PLATFORM_ROLE_HINTS[role]}
          </li>
        ))}
      </ul>

      <NewTeamMemberForm onCreated={changed} />
    </div>
  );
}

function TeamRow({
  user,
  isMe,
  onChanged,
}: {
  user: AdminPlatformUserDto;
  isMe: boolean;
  onChanged: (link?: AccountLink) => void;
}) {
  const [pending, setPending] = useState(false);
  const [error, setError] = useState<string | null>(null);

  async function patch(body: Record<string, string>): Promise<boolean> {
    const response = await fetch(`/api/admin/platform-users/${user.id}`, {
      method: "PATCH",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(body),
    });
    return response.ok;
  }

  async function changeRole(role: PlatformRole) {
    setPending(true);
    setError(null);
    try {
      if (!(await patch({ role }))) {
        setError("Չհաջողվեց փոխել դերը։");
        return;
      }
      onChanged();
    } finally {
      setPending(false);
    }
  }

  async function remove() {
    if (!confirm(`Հեռացնե՞լ ${user.email}-ը պլատֆորմի թիմից։`)) return;
    setPending(true);
    setError(null);
    try {
      const response = await fetch(`/api/admin/platform-users/${user.id}`, { method: "DELETE" });
      if (!response.ok) {
        setError("Չհաջողվեց հեռացնել։");
        return;
      }
      onChanged();
    } finally {
      setPending(false);
    }
  }

  return (
    <Tr>
      <Td className="font-medium text-ink">
        <span className="flex items-center gap-2">
          {user.name}
          {isMe ? <Badge tone="primary">Դուք</Badge> : null}
        </span>
        <span className="mt-1 block">
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
        {isMe ? (
          <Badge>{PLATFORM_ROLE_LABELS[user.role]}</Badge>
        ) : (
          <Select
            value={user.role}
            disabled={pending}
            onChange={(e) => changeRole(e.target.value as PlatformRole)}
            aria-label="Դեր"
          >
            {PLATFORM_ROLES.map((role) => (
              <option key={role} value={role}>
                {PLATFORM_ROLE_LABELS[role]}
              </option>
            ))}
          </Select>
        )}
      </Td>
      <Td className="text-right">
        <span className="inline-flex flex-wrap items-center justify-end gap-2">
          {error ? <span className="text-xs text-danger">{error}</span> : null}
          {!isMe ? (
            <AccountActions
              user={user}
              base={`/api/admin/platform-users/${user.id}`}
              disabled={pending}
              onLink={(link) => onChanged({ ...link, role: PLATFORM_ROLE_LABELS[user.role] })}
              onChanged={() => onChanged()}
              onError={setError}
            />
          ) : null}
          {!isMe ? (
            <Button size="sm" variant="secondary" disabled={pending} onClick={remove}>
              Հեռացնել
            </Button>
          ) : null}
        </span>
      </Td>
    </Tr>
  );
}

function NewTeamMemberForm({ onCreated }: { onCreated: (link: AccountLink) => void }) {
  const [name, setName] = useState("");
  const [email, setEmail] = useState("");
  const [role, setRole] = useState<PlatformRole>("moderator");
  const [status, setStatus] = useState<"idle" | "saving" | "error">("idle");
  const [errors, setErrors] = useState<Record<string, string[]>>({});

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault();
    setStatus("saving");
    setErrors({});
    try {
      const response = await fetch("/api/admin/platform-users", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ name: name.trim(), email: email.trim().toLowerCase(), role }),
      });
      if (!response.ok) {
        if (response.status === 422) {
          const json = (await response.json().catch(() => null)) as { errors?: Record<string, string[]> } | null;
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
        role: PLATFORM_ROLE_LABELS[role],
      };
      setName("");
      setEmail("");
      setRole("moderator");
      setStatus("idle");
      onCreated(created);
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
        <Input value={name} onChange={(e) => setName(e.target.value)} placeholder="Օրինակ՝ Նարեկ" required />
      </div>
      <div className="min-w-56 flex-1 space-y-1.5">
        <FieldLabel>Էլ. փոստ (մուտքանուն)</FieldLabel>
        <Input
          type="email"
          value={email}
          onChange={(e) => setEmail(e.target.value)}
          placeholder="name@servio.am"
          required
        />
      </div>
      <div className="space-y-1.5">
        <FieldLabel>Դեր</FieldLabel>
        <Select value={role} onChange={(e) => setRole(e.target.value as PlatformRole)}>
          {PLATFORM_ROLES.map((r) => (
            <option key={r} value={r}>
              {PLATFORM_ROLE_LABELS[r]}
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
            : "Չհաջողվեց ավելացնել։"}
        </p>
      ) : null}
    </form>
  );
}
