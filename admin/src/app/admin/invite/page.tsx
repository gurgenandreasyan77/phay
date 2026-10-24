"use client";

import { useEffect, useState } from "react";
import { useRouter } from "next/navigation";
import { AuthCard, ErrorBox, fieldError, homeFor, tokenFromHash } from "../_components/auth/AuthCard";
import { NewPasswordForm } from "../_components/auth/NewPasswordForm";

type Invitation = {
  name: string;
  email: string;
  businesses: string[];
  platformRole: string | null;
  twoFactorRequired: boolean;
  expiresAt: string;
};

/**
 * Opened from an invitation: the new person sets their own password (staff
 * never know it) and is signed in; if 2FA is required, its setup is next.
 */
export default function InvitePage() {
  const router = useRouter();
  const [token, setToken] = useState("");
  const [invitation, setInvitation] = useState<Invitation | null>(null);
  const [problem, setProblem] = useState<string | null>(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    const value = tokenFromHash();
    window.history.replaceState(null, "", window.location.pathname);

    fetch("/api/admin/invitation/lookup", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ token: value }),
    })
      .then(async (response) => {
        const json = (await response.json().catch(() => null)) as { data?: Invitation } | null;
        setToken(value);
        if (response.ok && json?.data) setInvitation(json.data);
        else setProblem(fieldError(json, "token") ?? "Հղումն անվավեր է կամ ժամկետանց։");
      })
      .catch(() => setProblem("Չհաջողվեց բացել հրավերը։"))
      .finally(() => setLoading(false));
  }, []);

  if (loading) return <AuthCard title="Հրավեր">Ստուգվում է…</AuthCard>;

  if (!invitation) {
    return (
      <AuthCard title="Հրավերն անվավեր է">
        <div className="space-y-3 text-sm text-ink">
          <ErrorBox>{problem}</ErrorBox>
          <p className="text-ink-muted">Խնդրեք ձեզ հրավիրողին ուղարկել նոր հղում։ Եթե արդեն ստեղծել եք գաղտնաբառը, պարզապես մուտք գործեք։</p>
        </div>
      </AuthCard>
    );
  }

  const where = invitation.platformRole ? "Phay պլատֆորմ" : invitation.businesses.join(", ");

  return (
    <AuthCard
      title={`Բարի գալուստ, ${invitation.name}`}
      description={
        <>
          Ստեղծեք գաղտնաբառ՝ մուտք գործելու համար{where ? ` (${where})` : ""}։ Ձեր մուտքանունը՝{" "}
          <span className="font-medium text-ink">{invitation.email}</span>
        </>
      }
    >
      <div className="space-y-4">
        {invitation.twoFactorRequired ? (
          <p className="rounded-md bg-info-light px-3 py-2 text-xs text-ink">
            Ձեր հաշվի համար պարտադիր է երկքայլ հաստատումը (2FA)։ Գաղտնաբառից հետո կմիացնեք այն հեռախոսի հավելվածով։
          </p>
        ) : null}
        <NewPasswordForm
          email={invitation.email}
          submitLabel="Ստեղծել գաղտնաբառ և մուտք գործել"
          onSubmit={async (password, confirmation) => {
            const response = await fetch("/api/admin/invitation/accept", {
              method: "POST",
              headers: { "Content-Type": "application/json" },
              body: JSON.stringify({ token, password, password_confirmation: confirmation }),
            });
            const json = (await response.json().catch(() => null)) as { data?: { user?: Parameters<typeof homeFor>[0] } } | null;
            if (response.ok) {
              router.push(homeFor(json?.data?.user));
              router.refresh();
              return null;
            }
            if (fieldError(json, "token")) {
              setInvitation(null);
              setProblem(fieldError(json, "token"));
              return null;
            }
            return { error: json };
          }}
        />
      </div>
    </AuthCard>
  );
}
