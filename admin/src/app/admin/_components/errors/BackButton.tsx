"use client";

import { useRouter } from "next/navigation";
import { buttonClasses } from "@/ui";

/** Back to the page the person came from; home when there is none (a typed or shared link). */
export function BackButton({ fallback = "/admin" }: { fallback?: string }) {
  const router = useRouter();

  return (
    <button
      type="button"
      className={buttonClasses("secondary")}
      onClick={() => (window.history.length > 1 ? router.back() : router.push(fallback))}
    >
      Հետ վերադառնալ
    </button>
  );
}
