"use client";

import { useEffect, useState } from "react";
import QRCode from "qrcode";
import { Button, Input } from "@/ui";
import { errorClasses, fieldError, labelClasses } from "./AuthCard";

type Step = { name: "password" } | { name: "scan"; secret: string; otpauthUrl: string } | { name: "codes"; codes: string[] };

/**
 * Turns 2FA on: the password again, then a QR code for the authenticator
 * app and its first code, then the recovery codes (shown only this once).
 */
export function TwoFactorSetup({ onDone, doneLabel = "Պատրաստ է" }: { onDone: () => void; doneLabel?: string }) {
  const [step, setStep] = useState<Step>({ name: "password" });
  const [password, setPassword] = useState("");
  const [code, setCode] = useState("");
  const [error, setError] = useState<string | null>(null);
  const [pending, setPending] = useState(false);

  /** The API's data, or null after showing why it failed. */
  async function call<T>(path: string, body: Record<string, string>, fallback: string): Promise<T | null> {
    setPending(true);
    setError(null);
    try {
      const response = await fetch(`/api/admin/security/${path}`, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(body),
      });
      const json = await response.json().catch(() => null);
      if (response.ok) return ((json as { data?: T } | null)?.data ?? null) as T | null;
      setError(
        response.status === 429
          ? "Չափազանց շատ փորձեր։ Սպասեք մեկ րոպե։"
          : (fieldError(json, "password") ?? fieldError(json, "code") ?? fallback),
      );
      return null;
    } catch {
      setError("Չհաջողվեց կապ հաստատել։");
      return null;
    } finally {
      setPending(false);
    }
  }

  async function begin(e: React.FormEvent) {
    e.preventDefault();
    const data = await call<{ secret: string; otpauthUrl: string }>("two-factor", { password }, "Չհաջողվեց սկսել։");
    if (data) setStep({ name: "scan", ...data });
  }

  async function confirm(e: React.FormEvent) {
    e.preventDefault();
    const data = await call<{ recoveryCodes: string[] }>("two-factor/confirm", { code }, "Կոդը սխալ է։");
    if (data) setStep({ name: "codes", codes: data.recoveryCodes });
  }

  if (step.name === "password") {
    return (
      <form onSubmit={begin} className="space-y-4">
        <p className="text-sm text-ink-muted">
          Ձեզ պետք կգա հեռախոսի նույնականացման հավելված՝ Google Authenticator, Microsoft Authenticator, 1Password կամ նմանատիպ։
          Սկզբից հաստատեք ձեր գաղտնաբառը։
        </p>
        <div className="space-y-1.5">
          <label htmlFor="tf-password" className={labelClasses}>
            Գաղտնաբառ
          </label>
          <Input id="tf-password" type="password" autoComplete="current-password" value={password} onChange={(e) => setPassword(e.target.value)} />
        </div>
        {error ? <p className={errorClasses}>{error}</p> : null}
        <Button type="submit" disabled={pending || !password}>
          {pending ? "Ստուգվում է…" : "Շարունակել"}
        </Button>
      </form>
    );
  }

  if (step.name === "scan") {
    return (
      <form onSubmit={confirm} className="space-y-4">
        <ol className="list-decimal space-y-1 pl-5 text-sm text-ink">
          <li>Բացեք հավելվածը և ավելացրեք նոր հաշիվ (+)։</li>
          <li>Սկանավորեք QR կոդը կամ մուտքագրեք բանալին ձեռքով։</li>
          <li>Մուտքագրեք հավելվածի ցույց տված 6 թվանշանանոց կոդը։</li>
        </ol>
        <div className="flex flex-col items-center gap-3 sm:flex-row sm:items-start">
          <QrImage value={step.otpauthUrl} />
          <div className="min-w-0 space-y-1 text-xs text-ink-muted">
            <p>Բանալի (ձեռքով մուտքագրելու համար)՝</p>
            <p className="break-all rounded-md bg-surface-secondary px-2 py-1.5 font-mono text-sm text-ink">
              {step.secret.replace(/(.{4})/g, "$1 ").trim()}
            </p>
            <p>Տեսակը՝ ըստ ժամանակի (TOTP), 6 թվանշան, 30 վայրկյան։</p>
          </div>
        </div>
        <div className="space-y-1.5">
          <label htmlFor="tf-code" className={`block ${labelClasses}`}>
            Կոդ հավելվածից
          </label>
          <Input
            id="tf-code"
            value={code}
            onChange={(e) => setCode(e.target.value)}
            inputMode="numeric"
            autoComplete="one-time-code"
            maxLength={6}
            className="max-w-40 text-center font-mono text-lg tracking-[0.3em]"
            placeholder="000000"
          />
        </div>
        {error ? <p className={errorClasses}>{error}</p> : null}
        <Button type="submit" disabled={pending || code.length < 6}>
          {pending ? "Ստուգվում է…" : "Միացնել 2FA"}
        </Button>
      </form>
    );
  }

  return <RecoveryCodes codes={step.codes} onDone={onDone} doneLabel={doneLabel} />;
}

/** The recovery codes, once: to copy, download or print, and a check that they were saved. */
export function RecoveryCodes({ codes, onDone, doneLabel }: { codes: string[]; onDone: () => void; doneLabel: string }) {
  const [saved, setSaved] = useState(false);
  const [copied, setCopied] = useState(false);
  const text = ["Phay — 2FA վերականգնման կոդեր", "Յուրաքանչյուրը գործում է մեկ անգամ։", "", ...codes].join("\n");

  function download() {
    const url = URL.createObjectURL(new Blob([text], { type: "text/plain" }));
    const link = document.createElement("a");
    link.href = url;
    link.download = "phay-recovery-codes.txt";
    link.click();
    URL.revokeObjectURL(url);
  }

  return (
    <div className="space-y-4">
      <div className="rounded-md border border-warning/40 bg-warning-light px-3 py-2 text-sm text-ink">
        <p className="font-medium">Պահեք այս կոդերը ապահով տեղում (ոչ հեռախոսում)։</p>
        <p className="mt-1 text-ink-muted">
          Եթե կորցնեք հեռախոսը, մուտք կգործեք դրանցից մեկով։ Յուրաքանչյուրը գործում է մեկ անգամ։ Դրանք այլևս չեն ցուցադրվի։
        </p>
      </div>
      <ul className="grid grid-cols-2 gap-2 rounded-md bg-surface-secondary p-3 font-mono text-sm text-ink">
        {codes.map((c) => (
          <li key={c}>{c}</li>
        ))}
      </ul>
      <div className="flex flex-wrap gap-2">
        <Button
          size="sm"
          variant="secondary"
          onClick={async () => {
            await navigator.clipboard.writeText(text);
            setCopied(true);
          }}
        >
          {copied ? "Պատճենված է" : "Պատճենել"}
        </Button>
        <Button size="sm" variant="secondary" onClick={download}>
          Ներբեռնել .txt
        </Button>
      </div>
      <label className="flex items-center gap-2 text-sm text-ink">
        <input type="checkbox" checked={saved} onChange={(e) => setSaved(e.target.checked)} />
        Ես պահել եմ կոդերը
      </label>
      <Button onClick={onDone} disabled={!saved}>
        {doneLabel}
      </Button>
    </div>
  );
}

function QrImage({ value }: { value: string }) {
  const [src, setSrc] = useState<string | null>(null);

  useEffect(() => {
    // Drawn here in the browser: the secret never goes to a QR service.
    QRCode.toDataURL(value, { margin: 1, width: 176, errorCorrectionLevel: "M" }).then(setSrc, () => setSrc(null));
  }, [value]);

  return (
    <div className="flex h-44 w-44 shrink-0 items-center justify-center rounded-md border border-border bg-white">
      {/* eslint-disable-next-line @next/next/no-img-element -- a data: URL drawn locally */}
      {src ? <img src={src} alt="QR կոդ 2FA-ի համար" width={176} height={176} /> : <span className="text-xs text-ink-muted">…</span>}
    </div>
  );
}
