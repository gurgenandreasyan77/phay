"use client";

import Link from "next/link";
import { useState } from "react";
import { Button, cn } from "../_components/ui";

type RequestBody =
  | { kind: "renew" }
  | { kind: "premium" }
  | { kind: "module"; module: string }
  | { kind: "plan"; plan_id: number };

/**
 * Writes the request to the platform in the business's chat, then points
 * there. `className` replaces the button's own look (cards in colour).
 */
export function RequestButton({
  body,
  label,
  primary,
  className,
  onDark,
}: {
  body: RequestBody;
  label: string;
  primary?: boolean;
  className?: string;
  /** Sits on a dark card: messages in light colours. */
  onDark?: boolean;
}) {
  const [state, setState] = useState<"idle" | "sending" | "sent" | "error">("idle");

  async function send() {
    setState("sending");
    const response = await fetch("/api/admin/subscription/request", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(body),
    });
    setState(response.ok ? "sent" : "error");
  }

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
          onClick={send}
        >
          {label}
        </button>
      ) : (
        <Button variant={primary ? "primary" : "secondary"} size={primary ? "md" : "sm"} disabled={state === "sending"} onClick={send}>
          {label}
        </Button>
      )}
      {state === "error" ? (
        <span className={cn("text-sm", onDark ? "text-[#f3b0a8]" : "text-danger")}>Չհաջողվեց ուղարկել։</span>
      ) : null}
    </div>
  );
}
