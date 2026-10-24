"use client";

import { useRouter } from "next/navigation";
import { Button } from "@/ui";
import { TwoFactorSetup } from "../_components/auth/TwoFactorSetup";

export function SetupTwoFactor({ required, home }: { required: boolean; home: string }) {
  const router = useRouter();

  async function signOut() {
    await fetch("/api/admin/session", { method: "DELETE" }).catch(() => null);
    router.push("/admin/login");
    router.refresh();
  }

  function go() {
    router.push(home);
    router.refresh();
  }

  return (
    <div className="space-y-5">
      {required ? <TwoFactorSetup onDone={go} doneLabel="Շարունակել վահանակ" /> : <Button onClick={go}>Բացել վահանակը</Button>}
      <button type="button" onClick={signOut} className="text-xs text-ink-muted hover:underline">
        Դուրս գալ
      </button>
    </div>
  );
}
