import type { ReactNode } from "react";
import type { LucideIcon } from "lucide-react";
import { cn } from "@/ui";

/**
 * The frame of every error page of the admin (404, 403, "something went
 * wrong", maintenance): a big code, what happened in plain Armenian and
 * where to go from here. `details` is a small box for the developer
 * (request id, the error's digest, in development its message).
 */
export function ErrorScreen({
  code,
  icon: Icon,
  tone = "primary",
  title,
  description,
  actions,
  details,
  fullScreen = false,
}: {
  code?: string;
  icon: LucideIcon;
  tone?: "primary" | "danger" | "warning";
  title: string;
  description: ReactNode;
  actions?: ReactNode;
  details?: ReactNode;
  /** Outside the admin's frame (global-error, maintenance): the whole screen. */
  fullScreen?: boolean;
}) {
  return (
    <div className={cn("flex items-center justify-center px-4 py-16", fullScreen ? "min-h-screen" : "min-h-[65vh]")}>
      <div className="w-full max-w-md text-center">
        <span
          className={cn(
            "mx-auto flex h-14 w-14 items-center justify-center rounded-full",
            tone === "danger" && "bg-danger-light text-danger",
            tone === "warning" && "bg-warning-light text-warning",
            tone === "primary" && "bg-primary-light text-primary",
          )}
        >
          <Icon className="h-7 w-7" aria-hidden />
        </span>
        {code ? (
          <p className="mt-5 text-5xl font-semibold tabular-nums tracking-tight text-ink" aria-hidden>
            {code}
          </p>
        ) : null}
        <h1 className={cn("text-xl font-semibold text-ink", code ? "mt-2" : "mt-5")}>{title}</h1>
        <div className="mt-2 text-sm leading-relaxed text-ink-muted">{description}</div>
        {actions ? <div className="mt-6 flex flex-wrap items-center justify-center gap-3">{actions}</div> : null}
        {details ? (
          <div className="mt-8 rounded-md border border-border bg-surface px-3.5 py-3 text-left text-xs text-ink-muted">
            {details}
          </div>
        ) : null}
        <p className="mt-10 text-xs font-medium tracking-wide text-ink-faint">Phay</p>
      </div>
    </div>
  );
}
