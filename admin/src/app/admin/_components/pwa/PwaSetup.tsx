"use client";

import { useEffect, useState } from "react";
import { Download, Share, X } from "lucide-react";
import { Button } from "@/ui";

/** Chrome's install event (not in the DOM types yet). */
type InstallEvent = Event & { prompt: () => Promise<void>; userChoice: Promise<{ outcome: "accepted" | "dismissed" }> };

const DISMISSED_KEY = "phay:install-dismissed";
// Asked again a month after «not now».
const ASK_AGAIN_MS = 30 * 24 * 60 * 60 * 1000;

function dismissedRecently(): boolean {
  try {
    const at = Number(localStorage.getItem(DISMISSED_KEY) ?? 0);
    return Date.now() - at < ASK_AGAIN_MS;
  } catch {
    return false;
  }
}

function standalone(): boolean {
  return (
    window.matchMedia("(display-mode: standalone)").matches ||
    (navigator as Navigator & { standalone?: boolean }).standalone === true
  );
}

/**
 * Phay as an installed app (PWA): registers the service worker in
 * production (and removes it in development, where it would keep old
 * builds), and offers a signed-in person to install it, once a month at
 * most: Chrome/Android/desktop with its own install dialog, iPhone with the
 * steps of «Add to Home Screen». Nothing shows inside the installed app.
 */
export function PwaSetup({ signedIn }: { signedIn: boolean }) {
  const [install, setInstall] = useState<InstallEvent | null>(null);
  const [ios, setIos] = useState(false);
  const [open, setOpen] = useState(false);

  useEffect(() => {
    if (!("serviceWorker" in navigator)) return;
    if (process.env.NODE_ENV === "production") {
      navigator.serviceWorker.register("/sw.js", { scope: "/" }).catch(() => {});
    } else {
      navigator.serviceWorker.getRegistrations().then((all) => all.forEach((r) => r.unregister()));
    }
  }, []);

  useEffect(() => {
    if (!signedIn || standalone() || dismissedRecently()) return;

    const onPrompt = (event: Event) => {
      event.preventDefault();
      setInstall(event as InstallEvent);
      setOpen(true);
    };
    window.addEventListener("beforeinstallprompt", onPrompt);

    // Safari on iPhone/iPad has no install event: it is done from the Share menu.
    const apple =
      /iphone|ipad|ipod/i.test(navigator.userAgent) || (navigator.platform === "MacIntel" && navigator.maxTouchPoints > 1);
    const timer = apple
      ? setTimeout(() => {
          setIos(true);
          setOpen(true);
        }, 4000)
      : undefined;

    return () => {
      window.removeEventListener("beforeinstallprompt", onPrompt);
      if (timer) clearTimeout(timer);
    };
  }, [signedIn]);

  function dismiss() {
    setOpen(false);
    try {
      localStorage.setItem(DISMISSED_KEY, String(Date.now()));
    } catch {
      // Private mode: asked again next time.
    }
  }

  async function installNow() {
    if (!install) return;
    await install.prompt();
    const { outcome } = await install.userChoice;
    setInstall(null);
    if (outcome === "accepted") setOpen(false);
    else dismiss();
  }

  if (!open || (!install && !ios)) return null;

  return (
    <div
      role="dialog"
      aria-label="Տեղադրել Phay-ը"
      className="fixed inset-x-3 bottom-3 z-50 mx-auto max-w-md rounded-2xl border border-border bg-surface p-4 shadow-xl sm:inset-x-auto sm:right-4 sm:bottom-4"
      style={{ marginBottom: "env(safe-area-inset-bottom)" }}
    >
      <div className="flex items-start gap-3">
        {/* eslint-disable-next-line @next/next/no-img-element */}
        <img src="/icons/phay-192.png" alt="" width={48} height={48} className="h-12 w-12 shrink-0 rounded-xl" />
        <div className="min-w-0 flex-1">
          <p className="font-semibold text-ink">Տեղադրեք Phay-ը</p>
          {ios ? (
            <p className="mt-1 text-sm text-ink-muted">
              Սեղմեք <Share className="inline h-4 w-4 align-[-3px] text-primary" /> «Կիսվել», ապա «Ավելացնել գլխավոր էկրանին»։
              Phay-ը կբացվի որպես առանձին հավելված։
            </p>
          ) : (
            <p className="mt-1 text-sm text-ink-muted">Բացեք վահանակը մեկ հպումով՝ որպես հավելված, առանց բրաուզերի։</p>
          )}
          {!ios ? (
            <div className="mt-3 flex flex-wrap gap-2">
              <Button size="sm" onClick={installNow}>
                <Download className="h-4 w-4" />
                Տեղադրել
              </Button>
              <Button size="sm" variant="secondary" onClick={dismiss}>
                Ոչ հիմա
              </Button>
            </div>
          ) : null}
        </div>
        <button type="button" aria-label="Փակել" onClick={dismiss} className="rounded-md p-1 text-ink-faint hover:text-ink">
          <X className="h-4 w-4" />
        </button>
      </div>
    </div>
  );
}
