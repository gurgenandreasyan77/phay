"use client";

import { useState } from "react";
import { useRouter } from "next/navigation";
import type { AccountSecurityDto } from "@/lib/adminApi";
import { Badge, Button, Card, Input, SectionCard, TableWrap, Td, Th, Thead, Tr } from "@/ui";
import { fieldError } from "../_components/auth/AuthCard";
import { RecoveryCodes, TwoFactorSetup } from "../_components/auth/TwoFactorSetup";
import { dateTime, deviceLabel } from "../_components/auth/format";

type Result = { ok: boolean; json: unknown; status: number };

async function send(path: string, method: string, body?: Record<string, unknown>): Promise<Result> {
  try {
    const response = await fetch(`/api/admin/security${path}`, {
      method,
      headers: { "Content-Type": "application/json" },
      body: body ? JSON.stringify(body) : undefined,
    });
    const json = response.status === 204 ? null : await response.json().catch(() => null);
    return { ok: response.ok, json, status: response.status };
  } catch {
    return { ok: false, json: null, status: 0 };
  }
}

function problem(result: Result, fallback: string): string {
  if (result.status === 429) return "Չափազանց շատ փորձեր։ Սպասեք մեկ րոպե։";
  return fieldError(result.json, "code") ?? fieldError(result.json, "password") ?? fieldError(result.json, "phone") ?? fallback;
}

/** The person's own account security: 2FA, confirmed email and phone, devices, recent events. */
export function SecurityPanel({ security }: { security: AccountSecurityDto }) {
  const router = useRouter();
  const refresh = () => router.refresh();

  return (
    <div className="space-y-10">
      <TwoFactorSection security={security} onChanged={refresh} />
      <ContactsSection security={security} onChanged={refresh} />
      {security.business ? <BusinessPolicy business={security.business} onChanged={refresh} /> : null}
      <SessionsSection security={security} onChanged={refresh} />
      <EventsSection security={security} />
    </div>
  );
}

function TwoFactorSection({ security, onChanged }: { security: AccountSecurityDto; onChanged: () => void }) {
  const [mode, setMode] = useState<"idle" | "setup" | "codes" | "disable">("idle");
  const [password, setPassword] = useState("");
  const [code, setCode] = useState("");
  const [codes, setCodes] = useState<string[] | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [pending, setPending] = useState(false);
  const { enabled, required, recoveryCodesLeft, confirmedAt } = security.twoFactor;

  function reset(next: typeof mode = "idle") {
    setMode(next);
    setPassword("");
    setCode("");
    setError(null);
    setCodes(null);
  }

  async function regenerate(e: React.FormEvent) {
    e.preventDefault();
    setPending(true);
    const result = await send("/two-factor/recovery-codes", "POST", { password });
    setPending(false);
    const next = (result.json as { data?: { recoveryCodes: string[] } } | null)?.data?.recoveryCodes;
    if (result.ok && next) setCodes(next);
    else setError(problem(result, "Չհաջողվեց։"));
  }

  async function disable(e: React.FormEvent) {
    e.preventDefault();
    setPending(true);
    const result = await send("/two-factor", "DELETE", { password, code });
    setPending(false);
    if (result.ok) {
      reset();
      onChanged();
    } else setError(problem(result, "Չհաջողվեց անջատել։"));
  }

  return (
    <SectionCard
      title="Երկքայլ հաստատում (2FA)"
      description="Մուտքի ժամանակ գաղտնաբառից բացի պահանջվում է կոդ հեռախոսի հավելվածից. գողացված գաղտնաբառը բավարար չի լինի։"
    >
      <Card className="space-y-4">
        <div className="flex flex-wrap items-center gap-2">
          {enabled ? <Badge tone="success">Միացված է</Badge> : <Badge tone="warning">Անջատված է</Badge>}
          {required ? <Badge tone="info">Պարտադիր է ձեր հաշվի համար</Badge> : null}
          {enabled ? (
            <span className="text-xs text-ink-muted">
              {dateTime(confirmedAt)}-ից · վերականգնման կոդեր՝ {recoveryCodesLeft}
            </span>
          ) : null}
        </div>

        {enabled && recoveryCodesLeft <= 2 && mode === "idle" ? (
          <p className="text-sm text-warning">Վերականգնման կոդերը քիչ են մնացել։ Ստեղծեք նորերը։</p>
        ) : null}

        {!enabled && mode !== "setup" ? <Button onClick={() => reset("setup")}>Միացնել 2FA</Button> : null}
        {mode === "setup" ? (
          <TwoFactorSetup
            onDone={() => {
              reset();
              onChanged();
            }}
          />
        ) : null}

        {enabled && mode === "idle" ? (
          <div className="flex flex-wrap gap-2">
            <Button variant="secondary" size="sm" onClick={() => reset("codes")}>
              Նոր վերականգնման կոդեր
            </Button>
            {!required ? (
              <Button variant="ghost" size="sm" onClick={() => reset("disable")}>
                Անջատել 2FA
              </Button>
            ) : null}
          </div>
        ) : null}

        {mode === "codes" && !codes ? (
          <form onSubmit={regenerate} className="flex flex-wrap items-end gap-2">
            <div className="space-y-1">
              <p className="text-xs text-ink-muted">Հին կոդերը կդադարեն գործել։ Հաստատեք գաղտնաբառը՝</p>
              <Input type="password" autoComplete="current-password" value={password} onChange={(e) => setPassword(e.target.value)} />
            </div>
            <Button type="submit" size="sm" disabled={pending || !password}>
              Ստեղծել
            </Button>
            <Button type="button" size="sm" variant="ghost" onClick={() => reset()}>
              Չեղարկել
            </Button>
          </form>
        ) : null}
        {mode === "codes" && codes ? (
          <RecoveryCodes
            codes={codes}
            doneLabel="Պատրաստ է"
            onDone={() => {
              reset();
              onChanged();
            }}
          />
        ) : null}

        {mode === "disable" ? (
          <form onSubmit={disable} className="flex flex-wrap items-end gap-2">
            <div className="space-y-1">
              <p className="text-xs text-ink-muted">Գաղտնաբառ</p>
              <Input type="password" autoComplete="current-password" value={password} onChange={(e) => setPassword(e.target.value)} />
            </div>
            <div className="space-y-1">
              <p className="text-xs text-ink-muted">Կոդ հավելվածից</p>
              <Input inputMode="numeric" maxLength={6} value={code} onChange={(e) => setCode(e.target.value)} className="w-28" />
            </div>
            <Button type="submit" size="sm" variant="danger" disabled={pending || !password || code.length < 6}>
              Անջատել
            </Button>
            <Button type="button" size="sm" variant="ghost" onClick={() => reset()}>
              Չեղարկել
            </Button>
          </form>
        ) : null}

        {error ? <p className="text-sm text-danger">{error}</p> : null}
      </Card>
    </SectionCard>
  );
}

function ContactsSection({ security, onChanged }: { security: AccountSecurityDto; onChanged: () => void }) {
  return (
    <SectionCard
      title="Էլ. փոստ և հեռախոս"
      description="Հաստատված հասցեին ենք ուղարկում գաղտնաբառի վերականգնման հղումը և կարևոր ծանուցումները։"
    >
      <div className="grid gap-4 lg:grid-cols-2">
        <EmailCard security={security} onChanged={onChanged} />
        <PhoneCard security={security} onChanged={onChanged} />
      </div>
    </SectionCard>
  );
}

function CodeForm({ onSubmit, pending }: { onSubmit: (code: string) => void; pending: boolean }) {
  const [code, setCode] = useState("");
  return (
    <form
      onSubmit={(e) => {
        e.preventDefault();
        onSubmit(code);
      }}
      className="flex flex-wrap items-end gap-2"
    >
      <Input
        inputMode="numeric"
        autoComplete="one-time-code"
        maxLength={6}
        placeholder="000000"
        value={code}
        onChange={(e) => setCode(e.target.value)}
        className="w-32 text-center font-mono tracking-[0.2em]"
      />
      <Button type="submit" size="sm" disabled={pending || code.length < 6}>
        Հաստատել
      </Button>
    </form>
  );
}

function EmailCard({ security, onChanged }: { security: AccountSecurityDto; onChanged: () => void }) {
  const [sent, setSent] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [pending, setPending] = useState(false);
  const verified = Boolean(security.email.verifiedAt);

  async function sendCode() {
    setPending(true);
    setError(null);
    const result = await send("/email/code", "POST");
    setPending(false);
    if (result.ok) setSent(true);
    else setError(problem(result, "Չհաջողվեց ուղարկել։"));
  }

  async function verify(code: string) {
    setPending(true);
    const result = await send("/email/verify", "POST", { code });
    setPending(false);
    if (result.ok) onChanged();
    else setError(problem(result, "Կոդը սխալ է։"));
  }

  return (
    <Card className="space-y-3">
      <div className="flex flex-wrap items-center justify-between gap-2">
        <p className="text-sm font-medium text-ink">{security.email.address}</p>
        {verified ? <Badge tone="success">Հաստատված է</Badge> : <Badge tone="warning">Հաստատված չէ</Badge>}
      </div>
      {!verified ? (
        <>
          {sent ? (
            <>
              <p className="text-xs text-ink-muted">Կոդն ուղարկված է ձեր փոստին (գործում է 10 րոպե)։</p>
              <CodeForm onSubmit={verify} pending={pending} />
            </>
          ) : null}
          <Button size="sm" variant={sent ? "ghost" : "secondary"} onClick={sendCode} disabled={pending}>
            {sent ? "Ուղարկել նորից" : "Ուղարկել հաստատման կոդ"}
          </Button>
        </>
      ) : null}
      {error ? <p className="text-sm text-danger">{error}</p> : null}
    </Card>
  );
}

function PhoneCard({ security, onChanged }: { security: AccountSecurityDto; onChanged: () => void }) {
  const [phone, setPhone] = useState("");
  const [sentTo, setSentTo] = useState<string | null>(security.phone.pending);
  const [editing, setEditing] = useState(!security.phone.number);
  const [error, setError] = useState<string | null>(null);
  const [pending, setPending] = useState(false);

  async function sendCode(e: React.FormEvent) {
    e.preventDefault();
    setPending(true);
    setError(null);
    const result = await send("/phone/code", "POST", { phone });
    setPending(false);
    if (result.ok) setSentTo((result.json as { data?: { phone?: string } } | null)?.data?.phone ?? phone);
    else setError(problem(result, "Չհաջողվեց ուղարկել SMS։"));
  }

  async function verify(code: string) {
    setPending(true);
    const result = await send("/phone/verify", "POST", { code });
    setPending(false);
    if (result.ok) {
      setSentTo(null);
      setEditing(false);
      onChanged();
    } else setError(problem(result, "Կոդը սխալ է։"));
  }

  return (
    <Card className="space-y-3">
      <div className="flex flex-wrap items-center justify-between gap-2">
        <p className="text-sm font-medium text-ink">{security.phone.number ?? "Հեռախոս նշված չէ"}</p>
        {security.phone.verifiedAt ? <Badge tone="success">Հաստատված է</Badge> : null}
      </div>
      {editing ? (
        <form onSubmit={sendCode} className="flex flex-wrap items-end gap-2">
          <Input
            type="tel"
            autoComplete="tel"
            placeholder="055 12 34 56"
            value={phone}
            onChange={(e) => setPhone(e.target.value)}
            className="w-44"
          />
          <Button type="submit" size="sm" variant="secondary" disabled={pending || !phone}>
            Ուղարկել SMS կոդ
          </Button>
        </form>
      ) : (
        <Button size="sm" variant="ghost" onClick={() => setEditing(true)}>
          Փոխել համարը
        </Button>
      )}
      {sentTo ? (
        <>
          <p className="text-xs text-ink-muted">Կոդն ուղարկված է {sentTo} համարին։</p>
          <CodeForm onSubmit={verify} pending={pending} />
        </>
      ) : null}
      {error ? <p className="text-sm text-danger">{error}</p> : null}
    </Card>
  );
}

function BusinessPolicy({ business, onChanged }: { business: NonNullable<AccountSecurityDto["business"]>; onChanged: () => void }) {
  const [pending, setPending] = useState(false);
  const [error, setError] = useState<string | null>(null);

  async function toggle(value: boolean) {
    setPending(true);
    setError(null);
    const result = await send("/business", "PUT", { require_two_factor: value });
    setPending(false);
    if (result.ok) onChanged();
    else setError(problem(result, "Չհաջողվեց պահպանել։"));
  }

  return (
    <SectionCard
      title={`«${business.name}» բիզնեսի թիմը`}
      description="Միացնելու դեպքում բիզնեսի բոլոր աշխատակիցները (ադմին, մոդերատոր) պետք է միացնեն 2FA՝ հաջորդ մուտքից սկսած։"
    >
      <Card className="flex flex-wrap items-center justify-between gap-3">
        <label className="flex items-center gap-2 text-sm text-ink">
          <input type="checkbox" checked={business.requireTwoFactor} disabled={pending} onChange={(e) => toggle(e.target.checked)} />
          Պահանջել 2FA բոլոր աշխատակիցներից
        </label>
        {error ? <span className="text-sm text-danger">{error}</span> : null}
      </Card>
    </SectionCard>
  );
}

function SessionsSection({ security, onChanged }: { security: AccountSecurityDto; onChanged: () => void }) {
  const [pending, setPending] = useState(false);
  const others = security.sessions.filter((s) => !s.current).length;

  async function revoke(id?: number) {
    setPending(true);
    await send(id ? `/sessions/${id}` : "/sessions", "DELETE");
    setPending(false);
    onChanged();
  }

  return (
    <SectionCard
      title="Սարքեր"
      description="Որտեղից եք մուտք գործել։ Անծանոթ սարք տեսնելիս դուրս հանեք այն և փոխեք գաղտնաբառը։ Մուտքը գործում է 7 օր։"
      actions={
        others > 0 ? (
          <Button size="sm" variant="secondary" disabled={pending} onClick={() => revoke()}>
            Դուրս գալ մյուս սարքերից
          </Button>
        ) : null
      }
    >
      <TableWrap>
        <Thead>
          <Th>Սարք</Th>
          <Th>IP</Th>
          <Th>Մուտք</Th>
          <Th>Վերջին ակտիվություն</Th>
          <Th />
        </Thead>
        <tbody>
          {security.sessions.map((session) => (
            <Tr key={session.id}>
              <Td>
                {deviceLabel(session.userAgent)}
                {session.current ? (
                  <Badge tone="success" className="ml-2">
                    Այս սարքը
                  </Badge>
                ) : null}
              </Td>
              <Td className="font-mono text-xs">{session.ipAddress ?? "—"}</Td>
              <Td className="text-xs">{dateTime(session.createdAt)}</Td>
              <Td className="text-xs">{dateTime(session.lastUsedAt)}</Td>
              <Td className="whitespace-nowrap text-right">
                {!session.current ? (
                  <Button size="sm" variant="ghost" disabled={pending} onClick={() => revoke(session.id)}>
                    Դուրս հանել
                  </Button>
                ) : null}
              </Td>
            </Tr>
          ))}
        </tbody>
      </TableWrap>
    </SectionCard>
  );
}

function EventsSection({ security }: { security: AccountSecurityDto }) {
  if (security.events.length === 0) return null;

  return (
    <SectionCard title="Վերջին իրադարձություններ" description="Մուտքեր, անհաջող փորձեր և հաշվի փոփոխություններ։">
      <TableWrap>
        <Thead>
          <Th>Ինչ</Th>
          <Th>Երբ</Th>
          <Th>Սարք</Th>
          <Th>IP</Th>
        </Thead>
        <tbody>
          {security.events.map((event) => (
            <Tr key={event.id}>
              <Td>
                <span className={event.warning ? "text-danger" : undefined}>{event.label}</span>
                {event.actor?.name ? <span className="ml-1 text-xs text-ink-muted">({event.actor.name})</span> : null}
              </Td>
              <Td className="text-xs">{dateTime(event.createdAt)}</Td>
              <Td className="text-xs">{deviceLabel(event.userAgent)}</Td>
              <Td className="font-mono text-xs">{event.ipAddress ?? "—"}</Td>
            </Tr>
          ))}
        </tbody>
      </TableWrap>
    </SectionCard>
  );
}
