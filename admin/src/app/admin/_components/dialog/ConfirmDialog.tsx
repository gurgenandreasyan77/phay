"use client";

import { AlertTriangle, HelpCircle } from "lucide-react";
import { useId, type ReactNode } from "react";
import { Button, cn } from "../ui";
import { Dialog, DialogClose } from "./Dialog";

/** What a confirmation says: plain data, so a server page can pass it. */
export type ConfirmContent = {
  title: string;
  /** What will happen, one or two sentences. */
  description?: string;
  /** Label and value lines, such as the price and the date. */
  details?: { label: string; value: string }[];
  confirmLabel?: string;
  cancelLabel?: string;
  /** `danger` for what can't be undone, such as a delete. */
  tone?: "primary" | "danger";
};

/**
 * «Are you sure?» before an action: its title, what happens, the figures
 * it concerns, and two buttons. Cancel has the focus, so Enter never
 * confirms by accident.
 */
export function ConfirmDialog({
  open,
  content,
  pending,
  error,
  onConfirm,
  onCancel,
  children,
}: {
  open: boolean;
  content: ConfirmContent;
  pending?: boolean;
  error?: string | null;
  onConfirm: () => void;
  onCancel: () => void;
  children?: ReactNode;
}) {
  const titleId = useId();
  const danger = content.tone === "danger";
  const Icon = danger ? AlertTriangle : HelpCircle;

  return (
    <Dialog open={open} onClose={() => (pending ? undefined : onCancel())} labelledBy={titleId} alert>
      <div className="space-y-4">
        <div className="flex items-start gap-3">
          <span
            className={cn(
              "flex h-10 w-10 shrink-0 items-center justify-center rounded-full",
              danger ? "bg-danger-light text-danger" : "bg-primary-light text-primary",
            )}
          >
            <Icon className="h-5 w-5" strokeWidth={1.9} />
          </span>
          <div className="min-w-0 flex-1 space-y-1 pt-0.5">
            <h2 id={titleId} className="text-base font-semibold text-ink">
              {content.title}
            </h2>
            {content.description ? <p className="text-sm leading-relaxed text-ink-muted">{content.description}</p> : null}
          </div>
          <DialogClose onClick={onCancel} />
        </div>

        {content.details && content.details.length > 0 ? (
          <dl className="divide-y divide-border rounded-lg bg-surface-secondary/70 px-3 text-sm">
            {content.details.map((line) => (
              <div key={line.label} className="flex items-baseline justify-between gap-3 py-2">
                <dt className="text-ink-muted">{line.label}</dt>
                <dd className="text-right font-medium text-ink">{line.value}</dd>
              </div>
            ))}
          </dl>
        ) : null}

        {children}

        {error ? <p className="text-sm text-danger">{error}</p> : null}

        <div className="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
          <Button variant="secondary" onClick={onCancel} disabled={pending} autoFocus>
            {content.cancelLabel ?? "Չեղարկել"}
          </Button>
          <Button variant={danger ? "danger" : "primary"} onClick={onConfirm} disabled={pending}>
            {pending ? "Մի պահ…" : (content.confirmLabel ?? "Այո, հաստատել")}
          </Button>
        </div>
      </div>
    </Dialog>
  );
}
