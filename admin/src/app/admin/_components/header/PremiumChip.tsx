"use client";

import Link from "next/link";
import { Crown } from "lucide-react";
import { useEffect, useState } from "react";
import { cn } from "@/ui";
import styles from "./premium.module.css";

/** The full invitation: the chip's tooltip. */
const INVITATION = "Անցեք Պրեմիումին՝ ավելին տեսնելու համար";

/** What premium gives, one line each, in turn beside the word «Պրեմիում». */
const HINTS = ["Ավելի խորը վերլուծություն", "Ավելի շատ AI հարցումներ", "Ավելի շատ մոդուլներ"];

const EVERY_MS = 4500;

function prefersReducedMotion(): boolean {
  return typeof window !== "undefined" && window.matchMedia("(prefers-reduced-motion: reduce)").matches;
}

/**
 * In the header of a business that isn't on premium: a quiet white pill
 * with the brand's seal (a gold crown on lagoon teal) that leads to the
 * premium block of the subscription page. On wide screens it goes through
 * what premium gives; on a phone it is the seal alone.
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
      className={cn(styles.chip, "mr-1 inline-flex h-11 shrink-0 items-center gap-2 rounded-full sm:h-9")}
      aria-label="Պրեմիում. ավելին՝ բաժանորդագրության էջում"
      title={INVITATION}
    >
      <span className={styles.seal} aria-hidden="true">
        <Crown className="h-3.5 w-3.5" strokeWidth={2} />
      </span>
      <span className={cn(styles.word, "hidden sm:inline")}>Պրեմիում</span>
      <span className="hidden items-center gap-2.5 xl:inline-flex" aria-hidden="true">
        <span className={styles.divider} />
        <span className="relative h-5 w-[12.5rem] overflow-hidden">
          <span key={index} className={styles.hint}>
            {HINTS[index]}
          </span>
        </span>
      </span>
    </Link>
  );
}
