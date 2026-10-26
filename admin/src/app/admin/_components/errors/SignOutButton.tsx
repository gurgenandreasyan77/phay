"use client";

import { useState } from "react";
import { useRouter } from "next/navigation";
import { buttonClasses } from "@/ui";

/** Sign out and go to sign-in (e.g. to enter with another account). */
export function SignOutButton({ variant = "secondary" }: { variant?: "primary" | "secondary" }) {
  const router = useRouter();
  const [busy, setBusy] = useState(false);

  return (
    <button
      type="button"
      disabled={busy}
      className={buttonClasses(variant)}
      onClick={async () => {
        setBusy(true);
        await fetch("/api/admin/session", { method: "DELETE" }).catch(() => null);
        router.push("/admin/login");
        router.refresh();
      }}
    >
      Դուրս գալ
    </button>
  );
}
