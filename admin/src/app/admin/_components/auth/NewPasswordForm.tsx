"use client";

import { useState } from "react";
import { Button, Input } from "@/ui";
import { PASSWORD_HINT, errorClasses, labelClasses, passwordProblem, passwordServerError } from "./AuthCard";

/**
 * A new password typed twice, checked here with the API's rules before it's
 * sent. `onSubmit` returns the API's JSON on a 422 (to show its message), or
 * null when it went through.
 */
export function NewPasswordForm({
  email,
  submitLabel,
  onSubmit,
}: {
  email?: string;
  submitLabel: string;
  onSubmit: (password: string, confirmation: string) => Promise<{ error: unknown } | null>;
}) {
  const [password, setPassword] = useState("");
  const [confirmation, setConfirmation] = useState("");
  const [error, setError] = useState<string | null>(null);
  const [pending, setPending] = useState(false);

  async function submit(e: React.FormEvent) {
    e.preventDefault();
    const problem = passwordProblem(password, confirmation);
    setError(problem);
    if (problem) return;

    setPending(true);
    const result = await onSubmit(password, confirmation).catch(() => ({ error: null }));
    setPending(false);
    if (result) setError(passwordServerError(result.error) ?? "Չհաջողվեց պահպանել։ Փորձեք նորից։");
  }

  return (
    <form onSubmit={submit} className="space-y-5">
      {/* Lets the browser's password manager save it under the right login. */}
      {email ? <input type="email" autoComplete="username" value={email} readOnly hidden /> : null}
      <div className="space-y-1.5">
        <label htmlFor="new-password" className={labelClasses}>
          Նոր գաղտնաբառ
        </label>
        <Input id="new-password" type="password" autoComplete="new-password" value={password} onChange={(e) => setPassword(e.target.value)} />
        <p className="text-xs text-ink-muted">{PASSWORD_HINT}</p>
      </div>
      <div className="space-y-1.5">
        <label htmlFor="confirm-password" className={labelClasses}>
          Կրկնեք գաղտնաբառը
        </label>
        <Input
          id="confirm-password"
          type="password"
          autoComplete="new-password"
          value={confirmation}
          onChange={(e) => setConfirmation(e.target.value)}
        />
      </div>
      {error ? <p className={errorClasses}>{error}</p> : null}
      <Button type="submit" disabled={pending} className="w-full">
        {pending ? "Պահպանվում է…" : submitLabel}
      </Button>
    </form>
  );
}
