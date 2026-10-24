import type { ReactNode } from "react";
import { Card } from "@/ui";

/** The frame of the sign-in pages (login, invitation, password reset, 2FA setup). */
export function AuthCard({
  title,
  description,
  children,
  wide = false,
}: {
  title: string;
  description?: ReactNode;
  children: ReactNode;
  wide?: boolean;
}) {
  return (
    <div className="flex min-h-screen items-center justify-center px-4 py-16">
      <div className={wide ? "w-full max-w-lg space-y-6" : "w-full max-w-sm space-y-6"}>
        <div className="flex flex-col items-center gap-3 text-center">
          <span className="flex h-11 w-11 items-center justify-center rounded-md bg-primary text-base font-semibold text-primary-foreground">
            S
          </span>
          <div>
            <h1 className="text-xl font-semibold text-ink">{title}</h1>
            {description ? <p className="mt-1 text-sm text-ink-muted">{description}</p> : null}
          </div>
        </div>
        <Card>{children}</Card>
      </div>
    </div>
  );
}

export const labelClasses = "text-sm font-medium text-ink";
export const errorClasses = "text-sm text-danger";

export function ErrorBox({ children }: { children: ReactNode }) {
  return (
    <div className="rounded-md border border-danger bg-danger-light p-3.5" role="alert">
      <p className="text-sm font-medium text-danger">{children}</p>
    </div>
  );
}

/** The token of a one-time link: after # so it never reaches a server log. */
export function tokenFromHash(): string {
  if (typeof window === "undefined") return "";
  return decodeURIComponent(window.location.hash.replace(/^#/, ""));
}

/** First message of a Laravel 422 for a field, if any. */
export function fieldError(json: unknown, field: string): string | null {
  const errors = (json as { errors?: Record<string, string[]> } | null)?.errors;
  return errors?.[field]?.[0] ?? null;
}

export const PASSWORD_HINT = "Առնվազն 10 նիշ՝ տառեր և թվեր։ Լավագույնը՝ մի քանի բառից կազմված արտահայտություն։";

/** Same rules as the API (Password::defaults): 10+ characters, letters and digits. */
export function passwordProblem(password: string, confirmation: string): string | null {
  if (password.length < 10) return "Գաղտնաբառը պետք է լինի առնվազն 10 նիշ։";
  if (!/\p{L}/u.test(password) || !/\d/.test(password)) return "Գաղտնաբառը պետք է պարունակի և՛ տառեր, և՛ թվեր։";
  if (password !== confirmation) return "Գաղտնաբառերը չեն համընկնում։";
  return null;
}

/** Laravel's messages about a rejected new password, in Armenian. */
export function passwordServerError(json: unknown): string | null {
  const message = fieldError(json, "password");
  if (!message) return null;
  if (/data leak|compromised/i.test(message)) return "Այս գաղտնաբառը հայտնի է արտահոսքերից։ Ընտրեք ուրիշը։";
  return "Գաղտնաբառը չի համապատասխանում պահանջներին։ " + PASSWORD_HINT;
}

/** Where someone goes after signing in. */
export function homeFor(user: { platformRole?: string | null; isSuperAdmin?: boolean; twoFactorSetupRequired?: boolean } | undefined): string {
  if (user?.twoFactorSetupRequired) return "/admin/setup-2fa";
  return user?.platformRole || user?.isSuperAdmin ? "/admin/platform" : "/admin";
}
