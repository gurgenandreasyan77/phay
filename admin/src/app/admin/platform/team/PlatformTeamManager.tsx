"use client";

import { useState } from "react";
import { useRouter } from "next/navigation";
import type { AdminPlatformUserDto, PlatformRole } from "@/lib/adminApi";
import { PLATFORM_ROLE_HINTS, PLATFORM_ROLE_LABELS, PLATFORM_ROLES } from "@/lib/roles";
import { Badge, Button, EmptyRow, FieldLabel, Input, Select, TableWrap, Td, Th, Thead, Tr } from "../../_components/ui";
import { CopyLoginButton, CredentialsNotice, generatePassword, type Credentials } from "../credentials";

/** Servio's own people: super admins and platform moderators (super admin only). */
export function PlatformTeamManager({ users, meId }: { users: AdminPlatformUserDto[]; meId: number }) {
  const router = useRouter();
  const [credentials, setCredentials] = useState<Credentials | null>(null);

  function changed(next?: Credentials) {
    if (next) setCredentials(next);
    router.refresh();
  }

  return (
    <div className="space-y-6">
      {credentials ? <CredentialsNotice credentials={credentials} onDismiss={() => setCredentials(null)} /> : null}

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
  onChanged: (credentials?: Credentials) => void;
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

  async function resetPassword() {
    if (!confirm(`Նոր գաղտնաբառ ստեղծե՞լ ${user.email}-ի համար։ Հինը այլևս չի աշխատի։`)) return;
    const password = generatePassword();
    setPending(true);
    setError(null);
    try {
      if (!(await patch({ password }))) {
        setError("Չհաջողվեց փոխել գաղտնաբառը։");
        return;
      }
      onChanged({ name: user.name, email: user.email, password, role: PLATFORM_ROLE_LABELS[user.role] });
    } finally {
      setPending(false);
    }
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
          <Button size="sm" variant="secondary" disabled={pending} onClick={resetPassword}>
            Նոր գաղտնաբառ
          </Button>
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

function NewTeamMemberForm({ onCreated }: { onCreated: (credentials: Credentials) => void }) {
  const [name, setName] = useState("");
  const [email, setEmail] = useState("");
  const [role, setRole] = useState<PlatformRole>("moderator");
  const [password, setPassword] = useState(generatePassword);
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
        body: JSON.stringify({ name: name.trim(), email: email.trim().toLowerCase(), password, role }),
      });
      if (!response.ok) {
        if (response.status === 422) {
          const json = (await response.json().catch(() => null)) as { errors?: Record<string, string[]> } | null;
          setErrors(json?.errors ?? { email: ["invalid"] });
        }
        setStatus("error");
        return;
      }
      const created = { name: name.trim(), email: email.trim().toLowerCase(), password, role: PLATFORM_ROLE_LABELS[role] };
      setName("");
      setEmail("");
      setRole("moderator");
      setPassword(generatePassword());
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
        Ավելացնել
      </Button>
      {status === "error" ? (
        <p className="basis-full text-sm text-danger">
          {errors.email
            ? "Այս էլ. փոստով մուտք արդեն կա կամ հասցեն սխալ է։"
            : errors.password
              ? "Գաղտնաբառը պետք է լինի առնվազն 8 նիշ։"
              : "Չհաջողվեց ավելացնել։"}
        </p>
      ) : null}
    </form>
  );
}
