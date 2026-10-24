"use client";

import { useState } from "react";
import type { AccountLinkDto, AccountSecurityFlags } from "@/lib/adminApi";
import { Badge, Button } from "../_components/ui";
import { dateTime } from "../_components/auth/format";

/** A one-time link just made for someone, with who it is for. */
export type AccountLink = AccountLinkDto & { kind: "invitation" | "reset"; name?: string; email: string; role?: string };

/**
 * An invitation or password reset link, ready to pass on by messenger when
 * the email didn't go (or as well). Staff never set or see a password: the
 * person sets their own from the link, once, before it expires.
 */
export function LinkNotice({ link, onDismiss }: { link: AccountLink; onDismiss: () => void }) {
  const [copied, setCopied] = useState(false);
  const what = link.kind === "invitation" ? "Հրավեր Servio ադմին վահանակ" : "Գաղտնաբառի վերականգնում Servio ադմին վահանակում";
  const action = link.kind === "invitation" ? "ստեղծեք ձեր գաղտնաբառը" : "ստեղծեք նոր գաղտնաբառ";
  const text = [
    `${what}${link.name ? `՝ ${link.name}` : ""}`,
    link.role ? `Դեր՝ ${link.role}` : null,
    `Մուտքանուն՝ ${link.email}`,
    `Բացեք հղումը և ${action} (գործում է մինչև ${dateTime(link.expiresAt)}, մեկ անգամ)՝`,
    link.url,
  ]
    .filter(Boolean)
    .join("\n");

  async function copy() {
    await navigator.clipboard.writeText(text);
    setCopied(true);
  }

  return (
    <div className="space-y-2 rounded-lg border border-success/30 bg-success-light px-4 py-3 text-sm text-ink">
      <p className="font-medium">
        {link.emailed
          ? `Հղումն ուղարկված է ${link.email} հասցեին։ Կարող եք նաև փոխանցել այն ուղիղ (WhatsApp, Telegram)։`
          : `Նամակը չուղարկվեց. փոխանցեք հղումը ${link.email}-ին ուղիղ (WhatsApp, Telegram)։`}
      </p>
      <p className="text-xs text-ink-muted">
        Հղումը անձնական է. մի բացեք այն ինքներդ և մի տեղադրեք ընդհանուր չատերում։ Եթե այն սխալ մարդու մոտ հայտնվի, ստեղծեք նորը. հինը կդադարի գործել։
      </p>
      <pre className="whitespace-pre-wrap break-all rounded-md bg-surface px-3 py-2 font-mono text-xs">{text}</pre>
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

/** Where a login stands: invited or active, 2FA, confirmed email/phone. */
export function AccountBadges({ user }: { user: AccountSecurityFlags }) {
  if (user.activated === undefined) return null;

  return (
    <span className="flex flex-wrap gap-1">
      {user.activated ? null : <Badge tone="pending">Հրավիրված</Badge>}
      {user.activated ? user.twoFactorEnabled ? <Badge tone="success">2FA</Badge> : <Badge tone="warning">Առանց 2FA</Badge> : null}
      {user.emailVerified ? <Badge tone="default">✓ փոստ</Badge> : null}
      {user.phoneVerified ? <Badge tone="default">✓ հեռախոս</Badge> : null}
    </span>
  );
}

/**
 * The account actions of a row: a fresh invitation (not activated yet) or a
 * reset link, and turning off a lost 2FA. `base` is the login's API path.
 */
export function AccountActions({
  user,
  base,
  disabled,
  onLink,
  onChanged,
  onError,
}: {
  user: AccountSecurityFlags & { name: string; email: string };
  base: string;
  disabled: boolean;
  onLink: (link: AccountLink) => void;
  onChanged: () => void;
  onError: (message: string | null) => void;
}) {
  const [pending, setPending] = useState(false);

  async function makeLink(kind: AccountLink["kind"]) {
    const question =
      kind === "invitation"
        ? `Նոր հրավերի հղում ստեղծե՞լ ${user.email}-ի համար։ Նախորդը կդադարի գործել։`
        : `Գաղտնաբառի վերականգնման հղում ստեղծե՞լ ${user.email}-ի համար։ Ներկայիս գաղտնաբառը կգործի, մինչև նա ստեղծի նորը։`;
    if (!confirm(question)) return;
    setPending(true);
    onError(null);
    try {
      const response = await fetch(`${base}/${kind === "invitation" ? "invitation" : "password-reset"}`, { method: "POST" });
      const json = (await response.json().catch(() => null)) as { data?: AccountLinkDto; errors?: Record<string, string[]> } | null;
      if (response.ok && json?.data) onLink({ ...json.data, kind, name: user.name, email: user.email });
      else onError(json?.errors?.user?.[0] ?? "Չհաջողվեց ստեղծել հղումը։");
    } finally {
      setPending(false);
    }
  }

  async function resetTwoFactor() {
    if (!confirm(`Անջատե՞լ ${user.email}-ի 2FA-ն (կորցրել է հեռախոսը)։ Նա դուրս կգա բոլոր սարքերից և հաջորդ մուտքին նորից կմիացնի այն։ Համոզվեք, որ խոսում եք հենց այդ մարդու հետ։`)) {
      return;
    }
    setPending(true);
    onError(null);
    try {
      const response = await fetch(`${base}/two-factor`, { method: "DELETE" });
      if (response.ok) onChanged();
      else onError("Չհաջողվեց անջատել 2FA-ն։");
    } finally {
      setPending(false);
    }
  }

  const busy = disabled || pending;

  return (
    <>
      {user.activated === false ? (
        <Button size="sm" variant="secondary" disabled={busy} onClick={() => makeLink("invitation")}>
          Նոր հրավեր
        </Button>
      ) : (
        <Button size="sm" variant="secondary" disabled={busy} onClick={() => makeLink("reset")}>
          Վերականգնման հղում
        </Button>
      )}
      {user.twoFactorEnabled ? (
        <Button size="sm" variant="ghost" disabled={busy} onClick={resetTwoFactor}>
          Անջատել 2FA
        </Button>
      ) : null}
    </>
  );
}

/** Copies a login (email) to the clipboard, e.g. to remind someone what they sign in with. */
export function CopyLoginButton({ email }: { email: string }) {
  const [copied, setCopied] = useState(false);

  async function copy() {
    await navigator.clipboard.writeText(email);
    setCopied(true);
    setTimeout(() => setCopied(false), 1500);
  }

  return (
    <button type="button" onClick={copy} className="text-xs text-primary hover:underline" title="Պատճենել մուտքանունը">
      {copied ? "Պատճենված է" : "Պատճենել"}
    </button>
  );
}
