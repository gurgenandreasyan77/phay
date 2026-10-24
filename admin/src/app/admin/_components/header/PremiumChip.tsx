"use client";

import Link from "next/link";
import { Crown } from "lucide-react";
import { useEffect, useState } from "react";
import { cn } from "@/ui";
import styles from "./premium.module.css";

/** What premium gives, one line each, in turn. */
const HINTS = [
  "Անցեք Պրեմիումին՝ ավելին տեսնելու համար",
  "Պրեմիումում՝ ավելի խորը վերլուծություն",
  "Պրեմիումում՝ ավելի շատ AI հարցումներ",
  "Պրեմիումում՝ ավելի շատ մոդուլներ",
];

const EVERY_MS = 4500;

function prefersReducedMotion(): boolean {
  return typeof window !== "undefined" && window.matchMedia("(prefers-reduced-motion: reduce)").matches;
}

/**
 * In the header of a business that isn't on premium: a gold chip that
 * leads to the premium block of the subscription page. On wide screens it
 * goes through what premium gives; on a phone it is the crown alone.
 */
export function PremiumChip() {
  const [index, setIndex] = useState(0);

  useEffect(() => {
    if (prefersReducedMotion()) return;
    const timer = window.setInterval(() => setIndex((i) => (i + 1) % HINTS.length), EVERY_MS);
    return () => window.clearInterval(timer);
  }, []);

  return (
    <Link
      href="/admin/subscription#premium"
      className={cn(
        styles.chip,
        "mr-1 inline-flex h-9 shrink-0 items-center gap-2 rounded-full px-2.5 text-xs font-semibold sm:px-3",
      )}
      aria-label="Պրեմիում. ավելին՝ բաժանորդագրության էջում"
      title={HINTS[0]}
    >
      <Crown className={cn(styles.crown, "h-4 w-4 shrink-0")} strokeWidth={1.9} />
      <span className={cn(styles.gold, "hidden sm:inline xl:hidden")}>Պրեմիում</span>
      <span className="hidden w-[17.5rem] overflow-hidden whitespace-nowrap xl:inline-block" aria-live="off">
        <span key={index} className={cn(styles.hint, styles.gold)}>
          {HINTS[index]}
        </span>
      </span>
    </Link>
  );
}
