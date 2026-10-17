"use client";

import { useState } from "react";
import { Button } from "../_components/ui";

/** 12 characters without look-alikes (0/O, 1/l/I), easy to dictate on the phone. */
export function generatePassword(): string {
  const alphabet = "abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789";
  const bytes = crypto.getRandomValues(new Uint32Array(12));
  return Array.from(bytes, (n) => alphabet[n % alphabet.length]).join("");
}

export type Credentials = { name?: string; email: string; password: string; role?: string };

/**
 * A login and its new password, ready to send to the person. Passwords are
 * stored hashed, so this is the only time it can be shown; if they lose it,
 * "New password" makes another one.
 */
export function CredentialsNotice({ credentials, onDismiss }: { credentials: Credentials; onDismiss: () => void }) {
  const [copied, setCopied] = useState(false);
  const origin = typeof window !== "undefined" ? window.location.origin : "";
  const text = [
    credentials.name ? `Անուն՝ ${credentials.name}` : null,
    credentials.role ? `Դեր՝ ${credentials.role}` : null,
    `Մուտք՝ ${origin}/admin/login`,
    `Էլ. փոստ (մուտքանուն)՝ ${credentials.email}`,
    `Գաղտնաբառ՝ ${credentials.password}`,
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
        Ուղարկեք այս տվյալները աշխատակցին։ Գաղտնաբառը պահվում է գաղտնագրված և այլևս չի ցուցադրվի. եթե այն մոռանան,
        սեղմեք «Նոր գաղտնաբառ»։
      </p>
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
