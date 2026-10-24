"use client";

import { useEffect, useState } from "react";
import Link from "next/link";
import { AuthCard, ErrorBox, fieldError, tokenFromHash } from "../_components/auth/AuthCard";
import { NewPasswordForm } from "../_components/auth/NewPasswordForm";

/** Opened from the reset email: a new password, then sign in (2FA is still asked). */
export default function ResetPasswordPage() {
  const [token, setToken] = useState<string | null>(null);
  const [state, setState] = useState<{ status: "loading" | "ready" | "invalid" | "done"; email?: string; message?: string }>({
    status: "loading",
  });

  useEffect(() => {
    const value = tokenFromHash();
    // Out of the address bar (and the history) as soon as it's read.
    window.history.replaceState(null, "", window.location.pathname);

    fetch("/api/admin/password/reset/lookup", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ token: value }),
    })
      .then(async (response) => {
        const json = (await response.json().catch(() => null)) as { data?: { email: string } } | null;
        setToken(value);
        setState(response.ok && json?.data ? { status: "ready", email: json.data.email } : { status: "invalid", message: fieldError(json, "token") ?? undefined });
      })
      .catch(() => setState({ status: "invalid" }));
  }, []);

  if (state.status === "loading") {
    return <AuthCard title="Գաղտնաբառի վերականգնում">Ստուգվում է…</AuthCard>;
  }

  if (state.status === "invalid") {
    return (
      <AuthCard title="Հղումն անվավեր է">
        <div className="space-y-4 text-sm">
          <ErrorBox>{state.message ?? "Հղումն անվավեր է կամ ժամկետանց։"}</ErrorBox>
          <Link href="/admin/forgot-password" className="text-primary hover:underline">
            Խնդրել նոր հղում
          </Link>
        </div>
      </AuthCard>
    );
  }

  if (state.status === "done") {
    return (
      <AuthCard title="Գաղտնաբառը փոխված է">
        <div className="space-y-4 text-sm text-ink">
          <p>Բոլոր սարքերից դուրս ենք եկել։ Մուտք գործեք նոր գաղտնաբառով։</p>
          <Link href="/admin/login" className="text-primary hover:underline">
            Մուտք գործել
          </Link>
        </div>
      </AuthCard>
    );
  }

  return (
    <AuthCard title="Նոր գաղտնաբառ" description={state.email}>
      <NewPasswordForm
        email={state.email}
        submitLabel="Պահպանել գաղտնաբառը"
        onSubmit={async (password, confirmation) => {
          const response = await fetch("/api/admin/password/reset", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ token, password, password_confirmation: confirmation }),
          });
          const json = await response.json().catch(() => null);
          if (response.ok) {
            setState({ status: "done" });
            return null;
          }
          if (fieldError(json, "token")) {
            setState({ status: "invalid", message: fieldError(json, "token") ?? undefined });
            return null;
          }
          return { error: json };
        }}
      />
    </AuthCard>
  );
}
