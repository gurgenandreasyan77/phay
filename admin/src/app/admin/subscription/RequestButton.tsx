"use client";

import Link from "next/link";
import { useState } from "react";
import { Button } from "../_components/ui";

type RequestBody = { kind: "renew" } | { kind: "module"; module: string } | { kind: "plan"; plan_id: number };

/** Writes the request to the platform in the business's chat, then points there. */
export function RequestButton({ body, label, primary }: { body: RequestBody; label: string; primary?: boolean }) {
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
      <p className="text-sm text-success">
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
      <Button variant={primary ? "primary" : "secondary"} size={primary ? "md" : "sm"} disabled={state === "sending"} onClick={send}>
        {label}
      </Button>
      {state === "error" ? <span className="text-sm text-danger">Չհաջողվեց ուղարկել։</span> : null}
    </div>
  );
}
