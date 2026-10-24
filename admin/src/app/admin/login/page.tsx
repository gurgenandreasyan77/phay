"use client";

import { useState } from "react";
import Link from "next/link";
import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { z } from "zod";
import { useRouter } from "next/navigation";
import { Button, Input } from "../_components/ui";
import { AuthCard, ErrorBox, errorClasses, fieldError, homeFor, labelClasses } from "../_components/auth/AuthCard";

const schema = z.object({
  email: z.string().min(1, "Այս դաշտը պարտադիր է։").email("Խնդրում ենք նշել վավեր էլ. հասցե։"),
  password: z.string().min(1, "Այս դաշտը պարտադիր է։"),
});

type FormValues = z.infer<typeof schema>;

type SignedInUser = { isSuperAdmin?: boolean; platformRole?: string | null; twoFactorSetupRequired?: boolean };

/**
 * Sign-in: email and password, then (with 2FA) the 6-digit code from the
 * authenticator app or one of the recovery codes.
 */
export default function AdminLoginPage() {
  const router = useRouter();
  const [challenge, setChallenge] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);

  function done(user: SignedInUser | undefined) {
    router.push(homeFor(user));
    router.refresh();
  }

  if (challenge) {
    return (
      <TwoFactorStep
        challenge={challenge}
        onDone={done}
        onRestart={(message) => {
          setChallenge(null);
          setError(message);
        }}
      />
    );
  }

  return <PasswordStep error={error} setError={setError} onChallenge={setChallenge} onDone={done} />;
}

function PasswordStep({
  error,
  setError,
  onChallenge,
  onDone,
}: {
  error: string | null;
  setError: (message: string | null) => void;
  onChallenge: (challenge: string) => void;
  onDone: (user: SignedInUser | undefined) => void;
}) {
  const {
    register,
    handleSubmit,
    formState: { errors, isSubmitting },
  } = useForm<FormValues>({
    resolver: zodResolver(schema),
    defaultValues: { email: "", password: "" },
  });

  const onSubmit = async (values: FormValues) => {
    setError(null);

    try {
      const response = await fetch("/api/admin/session", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(values),
      });

      if (response.status === 429) {
        setError("Չափազանց շատ փորձեր։ Սպասեք մի քանի րոպե և փորձեք նորից։");
        return;
      }

      const json = (await response.json().catch(() => null)) as {
        data?: { twoFactor?: boolean; challenge?: string; user?: SignedInUser };
      } | null;

      if (!response.ok) {
        setError("Սխալ էլ. հասցե կամ գաղտնաբառ։");
        return;
      }

      if (json?.data?.twoFactor && json.data.challenge) {
        onChallenge(json.data.challenge);
        return;
      }

      onDone(json?.data?.user);
    } catch {
      setError("Չհաջողվեց կապ հաստատել։ Փորձեք նորից։");
    }
  };

  return (
    <AuthCard title="Մուտք" description="Մուտք գործեք ադմին վահանակ։">
      <form onSubmit={handleSubmit(onSubmit)} noValidate className="space-y-5">
        <div className="space-y-1.5">
          <label htmlFor="email" className={labelClasses}>
            Էլ. փոստ
          </label>
          <Input id="email" type="email" autoComplete="username" {...register("email")} />
          {errors.email ? <p className={errorClasses}>{errors.email.message}</p> : null}
        </div>

        <div className="space-y-1.5">
          <div className="flex items-baseline justify-between gap-2">
            <label htmlFor="password" className={labelClasses}>
              Գաղտնաբառ
            </label>
            <Link href="/admin/forgot-password" className="text-xs text-primary hover:underline">
              Մոռացե՞լ եք գաղտնաբառը
            </Link>
          </div>
          <Input id="password" type="password" autoComplete="current-password" {...register("password")} />
          {errors.password ? <p className={errorClasses}>{errors.password.message}</p> : null}
        </div>

        {error ? <ErrorBox>{error}</ErrorBox> : null}

        <Button type="submit" disabled={isSubmitting} className="w-full">
          {isSubmitting ? "Մուտք…" : "Մուտք գործել"}
        </Button>
      </form>
    </AuthCard>
  );
}

function TwoFactorStep({
  challenge,
  onDone,
  onRestart,
}: {
  challenge: string;
  onDone: (user: SignedInUser | undefined) => void;
  onRestart: (message: string | null) => void;
}) {
  const [useRecovery, setUseRecovery] = useState(false);
  const [code, setCode] = useState("");
  const [error, setError] = useState<string | null>(null);
  const [pending, setPending] = useState(false);

  async function submit(e: React.FormEvent) {
    e.preventDefault();
    if (!code.trim()) return;
    setPending(true);
    setError(null);

    try {
      const response = await fetch("/api/admin/session/two-factor", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(useRecovery ? { challenge, recovery_code: code } : { challenge, code }),
      });
      const json = (await response.json().catch(() => null)) as { data?: { user?: SignedInUser } } | null;

      if (response.ok) {
        onDone(json?.data?.user);
        return;
      }

      if (response.status === 429) {
        setError("Չափազանց շատ փորձեր։ Սպասեք մեկ րոպե։");
      } else if (fieldError(json, "challenge")) {
        onRestart(fieldError(json, "challenge"));
        return;
      } else {
        setError(useRecovery ? "Վերականգնման կոդը սխալ է կամ արդեն օգտագործված։" : "Կոդը սխալ է։ Ստուգեք, որ հեռախոսի ժամը ճիշտ է։");
      }
    } catch {
      setError("Չհաջողվեց կապ հաստատել։ Փորձեք նորից։");
    }
    setPending(false);
  }

  return (
    <AuthCard
      title="Երկքայլ հաստատում"
      description={
        useRecovery
          ? "Մուտքագրեք ձեր վերականգնման կոդերից մեկը (օր.՝ k7m2-x9qa)։ Յուրաքանչյուր կոդ գործում է մեկ անգամ։"
          : "Բացեք նույնականացման հավելվածը (Google Authenticator, Microsoft Authenticator...) և մուտքագրեք 6 թվանշանանոց կոդը։"
      }
    >
      <form onSubmit={submit} className="space-y-5">
        <div className="space-y-1.5">
          <label htmlFor="code" className={labelClasses}>
            {useRecovery ? "Վերականգնման կոդ" : "Կոդ"}
          </label>
          <Input
            id="code"
            value={code}
            onChange={(e) => setCode(e.target.value)}
            autoFocus
            autoComplete="one-time-code"
            inputMode={useRecovery ? "text" : "numeric"}
            maxLength={useRecovery ? 16 : 6}
            className="text-center font-mono text-lg tracking-[0.3em]"
            placeholder={useRecovery ? "xxxx-xxxx" : "000000"}
          />
        </div>

        {error ? <ErrorBox>{error}</ErrorBox> : null}

        <Button type="submit" disabled={pending || !code.trim()} className="w-full">
          {pending ? "Ստուգվում է…" : "Հաստատել"}
        </Button>

        <div className="flex flex-wrap justify-between gap-2 text-xs">
          <button
            type="button"
            className="text-primary hover:underline"
            onClick={() => {
              setUseRecovery(!useRecovery);
              setCode("");
              setError(null);
            }}
          >
            {useRecovery ? "Օգտագործել հավելվածի կոդը" : "Հեռախոսը մոտս չէ. վերականգնման կոդ"}
          </button>
          <button type="button" className="text-ink-muted hover:underline" onClick={() => onRestart(null)}>
            Ետ
          </button>
        </div>
      </form>
    </AuthCard>
  );
}
