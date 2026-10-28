"use client";

import { useEffect, useId, useState, type CSSProperties } from "react";
import type { AgentTermDto } from "@/lib/adminApi";
import { cn } from "@/ui";
import { AgentAvatar, agentTint, agentTintStyle } from "./AgentAvatar";
import { termTone } from "./terms";
import styles from "./terms.module.css";

const R = 54;
const FULL = 2 * Math.PI * R;

/** A number that runs up to its value on load (at once under reduced motion). */
export function useCountUp(value: number, duration = 1100, delay = 0): number {
  const [shown, setShown] = useState(0);

  useEffect(() => {
    const still = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
    let frame = 0;
    let start: number | null = null;
    const step = (now: number) => {
      if (still) {
        setShown(value);
        return;
      }
      start ??= now + delay;
      const t = Math.min(1, Math.max(0, (now - start) / duration));
      // Ease out: fast at first, settling on the value.
      setShown(Math.round(value * (1 - Math.pow(1 - t, 3))));
      if (t < 1) frame = requestAnimationFrame(step);
    };
    frame = requestAnimationFrame(step);
    return () => cancelAnimationFrame(frame);
  }, [value, duration, delay]);

  return shown;
}

/**
 * One AI agent's face inside a ring of its own term: the arc fills to the
 * days left out of the trial (7) or the month, a bright head runs to its
 * end, and the days count up on a pill. Amber and breathing near the end,
 * red and grey after it, a light running round a full ring when the plan
 * includes the agent.
 */
export function AgentTermRing({
  agentKey,
  term,
  size = 112,
  index = 0,
  title,
}: {
  agentKey: string;
  term: AgentTermDto;
  size?: number;
  index?: number;
  title?: string;
}) {
  const gradient = useId();
  const tone = termTone(term);
  const included = term.status === "included";
  const left = included ? term.span : Math.max(0, term.daysLeft ?? 0);
  const fraction = included ? 1 : term.span > 0 ? Math.min(1, left / term.span) : 0;
  const days = useCountUp(included ? 0 : left, 1100, index * 110);
  const tint = agentTint(agentKey);
  const stroke =
    tone === "warning"
      ? "#d08a1c"
      : tone === "danger"
        ? "var(--color-danger)"
        : tone === "muted"
          ? "var(--color-border)"
          : `url(#${gradient})`;
  const small = size < 80;

  return (
    <div
      className={cn(
        styles.ring,
        styles[tone],
        term.ending && styles.ending,
        included && styles.included,
        term.status === "expired" && styles.expired,
      )}
      style={{ ...agentTintStyle(agentKey), width: size, height: size, "--i": index } as CSSProperties}
      title={title}
    >
      <svg viewBox="0 0 120 120" className={styles.ringSvg} aria-hidden="true">
        <defs>
          <linearGradient id={gradient} x1="0" y1="0" x2="1" y2="1">
            <stop offset="0" stopColor={tint.from} />
            <stop offset="1" stopColor={tint.to} />
          </linearGradient>
        </defs>
        <circle cx="60" cy="60" r={R} fill="none" strokeWidth={small ? 9 : 7} className={styles.track} />
        <circle
          cx="60"
          cy="60"
          r={R}
          fill="none"
          strokeWidth={small ? 9 : 7}
          className={styles.arc}
          stroke={stroke}
          strokeDasharray={FULL}
          strokeDashoffset={FULL * (1 - fraction)}
          style={{ "--full": FULL } as CSSProperties}
        />
        {included ? (
          <g className={styles.halo}>
            <circle cx={60 + R} cy="60" r="4" fill="#fff" opacity="0.9" className={styles.cometDot} />
          </g>
        ) : fraction > 0.02 ? (
          <g className={styles.comet} style={{ "--angle": `${fraction * 360}deg` } as CSSProperties}>
            <circle cx={60 + R} cy="60" r={small ? 5 : 4.5} fill="#fff" className={styles.cometDot} />
            <circle cx={60 + R} cy="60" r={small ? 2.5 : 2.2} fill={tone === "tint" ? tint.tint : stroke} />
          </g>
        ) : null}
      </svg>
      <div className={styles.face}>
        <AgentAvatar
          agentKey={agentKey}
          size={Math.round(size * 0.7)}
          active={term.status !== "off" && term.status !== "expired"}
          delay={-index * 0.9}
        />
      </div>
      {small || term.status === "off" ? null : (
        <span className={styles.days} aria-label={included ? "Անժամկետ" : `${left} օր`}>
          {included ? (
            "∞"
          ) : (
            <>
              {days}
              <small>օր</small>
            </>
          )}
        </span>
      )}
    </div>
  );
}
