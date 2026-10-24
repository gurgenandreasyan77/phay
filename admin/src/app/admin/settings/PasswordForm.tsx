"use client";

import { useState } from "react";
import { Button, Card, FieldLabel, Input } from "@/ui";
import { PASSWORD_HINT, passwordProblem, passwordServerError } from "../_components/auth/AuthCard";

type Errors = Partial<Record<"current_password" | "password", string>>;

/** Changes the signed-in person's own password; the current one is required. */
export function PasswordForm() {
  const [current, setCurrent] = useState("");
  const [password, setPassword] = useState("");
  const [confirmation, setConfirmation] = useState("");
  const [errors, setErrors] = useState<Errors>({});
  const [status, setStatus] = useState<"idle" | "saving" | "saved" | "error">("idle");

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault();
    const local: Errors = {};
    const rules = passwordProblem(password, password);
    if (rules) local.password = rules;
    else if (password !== confirmation) local.password = "Նոր գաղտնաբառերը չեն համընկնում։";
    else if (password === current) local.password = "Նոր գաղտնաբառը պետք է տարբերվի ներկայիսից։";
    setErrors(local);
    if (Object.keys(local).length > 0) return;

    setStatus("saving");
    try {
      const response = await fetch("/api/admin/password", {
        method: "PUT",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ current_password: current, password, password_confirmation: confirmation }),
      });

      if (response.status === 422) {
        const json = (await response.json().catch(() => null)) as { errors?: Record<string, string[]> } | null;
        setErrors(
          json?.errors?.current_password
            ? { current_password: "Ներկայիս գաղտնաբառը սխալ է։" }
            : { password: passwordServerError(json) ?? "Նոր գաղտնաբառը չի համապատասխանում պահանջներին։" },
        );
        setStatus("idle");
        return;
      }

      if (!response.ok) {
        setStatus("error");
        return;
      }

      setCurrent("");
      setPassword("");
      setConfirmation("");
      setStatus("saved");
    } catch {
      setStatus("error");
    }
  }

  return (
    <Card>
      <form onSubmit={handleSubmit} className="space-y-4">
        <div className="grid gap-4 sm:grid-cols-3">
          <div className="space-y-1.5">
            <FieldLabel>Ներկայիս գաղտնաբառ</FieldLabel>
            <Input
              type="password"
              autoComplete="current-password"
              value={current}
              required
              onChange={(e) => setCurrent(e.target.value)}
            />
            {errors.current_password ? <p className="text-xs text-danger">{errors.current_password}</p> : null}
          </div>
          <div className="space-y-1.5">
            <FieldLabel>Նոր գաղտնաբառ</FieldLabel>
            <Input
              type="password"
              autoComplete="new-password"
              value={password}
              required
              minLength={10}
              onChange={(e) => setPassword(e.target.value)}
            />
            <p className="text-xs text-ink-muted">{PASSWORD_HINT}</p>
            {errors.password ? <p className="text-xs text-danger">{errors.password}</p> : null}
          </div>
          <div className="space-y-1.5">
            <FieldLabel>Կրկնեք նոր գաղտնաբառը</FieldLabel>
            <Input
              type="password"
              autoComplete="new-password"
              value={confirmation}
              required
              onChange={(e) => setConfirmation(e.target.value)}
            />
          </div>
        </div>

        <div className="flex flex-wrap items-center gap-3">
          <Button type="submit" disabled={status === "saving"}>
            {status === "saving" ? "Պահպանվում է…" : "Փոխել գաղտնաբառը"}
          </Button>
          {status === "saved" ? (
            <span className="text-sm text-success">Գաղտնաբառը փոխված է։ Մյուս սարքերից դուրս ենք եկել։</span>
          ) : null}
          {status === "error" ? <span className="text-sm text-danger">Չհաջողվեց փոխել գաղտնաբառը։</span> : null}
        </div>
      </form>
    </Card>
  );
}
