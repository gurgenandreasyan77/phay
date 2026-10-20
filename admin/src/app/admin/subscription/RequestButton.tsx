"use client";

import Link from "next/link";
import { useState } from "react";
import { ConfirmDialog, type ConfirmContent } from "../_components/dialog/ConfirmDialog";
import { Button, cn } from "../_components/ui";

type RequestBody =
  | { kind: "renew" }
  | { kind: "premium" }
  | { kind: "module"; module: string }
  | { kind: "plan"; plan_id: number };

/**
 * Asks «are you sure?» with what the request means, then writes it to the
 * platform in the business's chat and points there. `className` replaces
 * the button's own look (cards in colour).
 */
export function RequestButton({
  body,
  label,
  confirm,
  primary,
  className,
  onDark,
}: {
  body: RequestBody;
  label: string;
  confirm: ConfirmContent;
  primary?: boolean;
  className?: string;
  /** Sits on a dark card: messages in light colours. */
  onDark?: boolean;
}) {
  const [state, setState] = useState<"idle" | "asking" | "sending" | "sent" | "error">("idle");

  async function send() {
    setState("sending");
    try {
      const response = await fetch("/api/admin/subscription/request", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(body),
      });
      setState(response.ok ? "sent" : "error");
    } catch {
      setState("error");
    }
  }

  const dialog = (
    <ConfirmDialog
      open={state === "asking" || state === "sending" || state === "error"}
      content={confirm}
      pending={state === "sending"}
      error={state === "error" ? "Չհաջողվեց ուղարկել։ Փորձեք կրկին։" : null}
      onConfirm={send}
      onCancel={() => setState("idle")}
    />
  );

  if (state === "sent") {
    return (
      <p className={cn("text-sm", onDark ? "text-[#9fd8c2]" : "text-success")}>
        Հայտը ուղարկված է։ Servio-ի թիմը կպատասխանի{" "}
        <Link href="/admin/support" className="font-medium underline underline-offset-2">
          չատում
        </Link>
        ։
      </p>
    );
  }

  return (
    <div className="flex flex-wrap items-center gap-3">
      {className ? (
        <button
          type="button"
          className={cn(
            "inline-flex items-center justify-center gap-2 rounded-md px-4 text-sm font-medium disabled:cursor-not-allowed disabled:opacity-60",
            className,
          )}
          disabled={state === "sending"}
          onClick={() => setState("asking")}
        >
          {label}
        </button>
      ) : (
        <Button variant={primary ? "primary" : "secondary"} size={primary ? "md" : "sm"} disabled={state === "sending"} onClick={() => setState("asking")}>
          {label}
        </Button>
      )}
      {dialog}
    </div>
  );
}
