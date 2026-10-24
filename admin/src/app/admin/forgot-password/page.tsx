"use client";

import { useState } from "react";
import Link from "next/link";
import { Button, Input } from "@/ui";
import { AuthCard, ErrorBox, labelClasses } from "../_components/auth/AuthCard";

/**
 * Asks for a reset link by email. The answer is the same whether the email
 * has a login or not, so nobody can find out who uses the admin.
 */
export default function ForgotPasswordPage() {
  const [email, setEmail] = useState("");
  const [status, setStatus] = useState<"idle" | "sending" | "sent" | "error" | "throttled">("idle");

  async function submit(e: React.FormEvent) {
    e.preventDefault();
    setStatus("sending");
    try {
      const response = await fetch("/api/admin/password/forgot", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ email }),
      });
      setStatus(response.ok ? "sent" : response.status === 429 ? "throttled" : "error");
    } catch {
      setStatus("error");
    }
  }

  if (status === "sent") {
    return (
      <AuthCard title="Ստուգեք ձեր փոստը">
        <div className="space-y-4 text-sm text-ink">
          <p>
            Եթե <span className="font-medium">{email}</span> հասցեով մուտք կա, մենք ուղարկել ենք հղում նոր գաղտնաբառ
            ստեղծելու համար։ Հղումը գործում է 1 ժամ և միայն մեկ անգամ։
          </p>
          <p className="text-ink-muted">Նամակ չե՞ք ստացել. ստուգեք «Սպամ» թղթապանակը կամ դիմեք Phay-ի ադմինիստրատորին։</p>
          <Link href="/admin/login" className="text-primary hover:underline">
            Վերադառնալ մուտքի էջ
          </Link>
        </div>
      </AuthCard>
    );
  }

  return (
    <AuthCard title="Գաղտնաբառի վերականգնում" description="Նշեք ձեր էլ. փոստը, և մենք կուղարկենք հղում նոր գաղտնաբառի համար։">
      <form onSubmit={submit} className="space-y-5">
        <div className="space-y-1.5">
          <label htmlFor="email" className={labelClasses}>
            Էլ. փոստ
          </label>
          <Input id="email" type="email" autoComplete="username" required value={email} onChange={(e) => setEmail(e.target.value)} />
        </div>
        {status === "error" ? <ErrorBox>Չհաջողվեց ուղարկել։ Փորձեք նորից։</ErrorBox> : null}
        {status === "throttled" ? <ErrorBox>Չափազանց շատ հայցեր։ Փորձեք մի փոքր ուշ։</ErrorBox> : null}
        <Button type="submit" disabled={status === "sending" || !email} className="w-full">
          {status === "sending" ? "Ուղարկվում է…" : "Ուղարկել հղումը"}
        </Button>
        <Link href="/admin/login" className="block text-center text-xs text-ink-muted hover:underline">
          Վերադառնալ մուտքի էջ
        </Link>
      </form>
    </AuthCard>
  );
}
