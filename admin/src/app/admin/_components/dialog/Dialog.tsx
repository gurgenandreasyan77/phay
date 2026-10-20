"use client";

import { X } from "lucide-react";
import { useEffect, useRef, type ReactNode } from "react";
import { cn } from "../ui";
import styles from "./dialog.module.css";

/**
 * A modal window on the browser's own <dialog>: the page behind is out of
 * reach (focus stays inside), Esc and a click outside close it. `sheet`
 * slides it up from the bottom edge, for phones.
 */
export function Dialog({
  open,
  onClose,
  label,
  labelledBy,
  alert,
  sheet,
  className,
  children,
}: {
  open: boolean;
  onClose: () => void;
  /** Its name for screen readers when it has no visible title. */
  label?: string;
  /** Id of its visible title. */
  labelledBy?: string;
  /** Asks to confirm something: read out as an alert. */
  alert?: boolean;
  sheet?: boolean;
  className?: string;
  children: ReactNode;
}) {
  const ref = useRef<HTMLDialogElement>(null);

  useEffect(() => {
    const dialog = ref.current;
    if (!dialog) return;
    if (open && !dialog.open) {
      dialog.showModal();
      // The page doesn't scroll under a modal.
      document.documentElement.style.overflow = "hidden";
    }
    if (!open && dialog.open) dialog.close();
    return () => {
      if (!open) return;
      document.documentElement.style.overflow = "";
    };
  }, [open]);

  return (
    <dialog
      ref={ref}
      aria-label={label}
      aria-labelledby={labelledBy}
      role={alert ? "alertdialog" : undefined}
      className={cn(styles.dialog, sheet && styles.sheet)}
      onCancel={(event) => {
        event.preventDefault();
        onClose();
      }}
      onClick={(event) => {
        // The dialog itself is only hit outside its panel: on the backdrop.
        if (event.target === event.currentTarget) onClose();
      }}
    >
      {open ? (
        <div className={cn(styles.panel, "rounded-xl border border-border bg-surface p-5 shadow-lg", className)}>
          {sheet ? <div className={styles.handle} aria-hidden="true" /> : null}
          {children}
        </div>
      ) : null}
    </dialog>
  );
}

/** The cross in a dialog's corner. */
export function DialogClose({ onClick }: { onClick: () => void }) {
  return (
    <button
      type="button"
      onClick={onClick}
      className="-mr-1 -mt-1 flex h-8 w-8 shrink-0 items-center justify-center rounded-md text-ink-muted hover:bg-surface-secondary hover:text-ink"
      aria-label="Փակել"
    >
      <X className="h-4 w-4" strokeWidth={1.9} />
    </button>
  );
}
