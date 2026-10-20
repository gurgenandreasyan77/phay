import type { CSSProperties } from "react";
import type { SubscriptionDto } from "@/lib/adminApi";
import styles from "./subscription.module.css";

const RADIUS = 34;
const FULL = 2 * Math.PI * RADIUS;

/** Days left as a ring that fills on load: a trial counts from 14, a paid month from 30. */
export function DaysRing({ subscription }: { subscription: Pick<SubscriptionDto, "status" | "daysLeft"> }) {
  const { status, daysLeft } = subscription;
  const unlimited = status === "unlimited" || daysLeft === null;
  const left = unlimited ? 1 : Math.max(daysLeft, 0);
  const span = unlimited ? 1 : Math.max(left, status === "trial" ? 14 : 30);
  const offset = FULL * (1 - left / span);
  const tone = status === "expired" ? "var(--color-danger)" : status === "canceling" ? "var(--color-warning)" : undefined;

  return (
    <div className="relative h-24 w-24 shrink-0">
      <svg viewBox="0 0 84 84" className="h-full w-full -rotate-90" aria-hidden="true">
        <circle cx="42" cy="42" r={RADIUS} fill="none" strokeWidth="8" className={styles.ringTrack} />
        <circle
          cx="42"
          cy="42"
          r={RADIUS}
          fill="none"
          strokeWidth="8"
          className={styles.ringValue}
          strokeDasharray={FULL}
          strokeDashoffset={offset}
          style={{ "--full": FULL, ...(tone ? { stroke: tone } : {}) } as CSSProperties}
        />
      </svg>
      <div className="absolute inset-0 flex flex-col items-center justify-center text-center">
        <span className="text-2xl font-semibold leading-none text-ink">{unlimited ? "∞" : left}</span>
        <span className="mt-1 text-xs leading-tight text-ink-muted">{unlimited ? "անժամկետ" : "օր"}</span>
      </div>
    </div>
  );
}
