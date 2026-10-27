import { useId, type CSSProperties, type ReactElement } from "react";
import { cn } from "@/ui";
import styles from "./agents.module.css";

/** Each agent's colour: its shirt and accents, a soft glow and a light wash. */
export type AgentTint = { tint: string; soft: string; bg: string };

const TINTS: Record<string, AgentTint> = {
  analyst: { tint: "#3f6f62", soft: "rgba(63, 111, 98, 0.22)", bg: "#eaf2ee" },
  accountant: {
    tint: "#527589",
    soft: "rgba(82, 117, 137, 0.22)",
    bg: "#e7eff4",
  },
  tables: { tint: "#4f7a4a", soft: "rgba(79, 122, 74, 0.22)", bg: "#ebf3e8" },
  content: { tint: "#b4507a", soft: "rgba(180, 80, 122, 0.2)", bg: "#f7e9ef" },
  reels: { tint: "#b47a2b", soft: "rgba(185, 130, 50, 0.22)", bg: "#f8efe0" },
  editor: { tint: "#5a6788", soft: "rgba(90, 103, 136, 0.22)", bg: "#ecedf4" },
  designer: {
    tint: "#3d7f86",
    soft: "rgba(61, 127, 134, 0.22)",
    bg: "#e4f1f2",
  },
};

const FALLBACK: AgentTint = {
  tint: "#5d6a64",
  soft: "rgba(93, 106, 100, 0.2)",
  bg: "#eef0ee",
};

export function agentTint(key: string): AgentTint {
  return TINTS[key] ?? FALLBACK;
}

/** The CSS variables a card or a swimmer reads the agent's colours from. */
export function agentTintStyle(key: string): CSSProperties {
  const t = agentTint(key);
  return {
    "--tint": t.tint,
    "--tint-soft": t.soft,
    "--tint-bg": t.bg,
  } as CSSProperties;
}

type Look = {
  skin: string;
  hair: string;
  /** Hair behind the head (long hair). */
  back?: string;
  /** Hair over the forehead. */
  front: string;
  extra?: (tint: string) => ReactElement;
  badge: (tint: string) => ReactElement;
};

const SHORT = "M36 50C35 32 48 25 61 26c14 1 24 10 23 26-4-9-12-14-24-13-11 0-19 4-24 11z";
const LONG_BACK = "M33 54C31 31 46 22 60 22s29 9 27 32l2 30c-8 5-16 3-19-3H50c-3 6-11 8-19 3z";
const LONG_FRONT = "M36 52c1-17 12-25 25-24 13 1 23 10 23 22-7-7-16-11-27-10-9 1-16 5-21 12z";

const LOOKS: Record<string, Look> = {
  // Aram: short hair and glasses; a bar chart.
  analyst: {
    skin: "#e9c4a6",
    hair: "#2f2723",
    front: SHORT,
    extra: () => (
      <g fill="none" stroke="#202623" strokeWidth="1.6">
        <rect x="43.5" y="48" width="14" height="11" rx="4" />
        <rect x="62.5" y="48" width="14" height="11" rx="4" />
        <path d="M57.5 53h5" />
      </g>
    ),
    badge: (tint) => (
      <g fill={tint}>
        <rect x="86" y="96" width="4" height="7" rx="1.2" />
        <rect x="92" y="91" width="4" height="12" rx="1.2" />
        <rect x="98" y="87" width="4" height="16" rx="1.2" fillOpacity="0.7" />
      </g>
    ),
  },
  // Ani: hair in a bun, small earrings; a calculator.
  accountant: {
    skin: "#f2d6c1",
    hair: "#4a3328",
    front: LONG_FRONT,
    extra: (tint) => (
      <>
        <circle cx="60" cy="23" r="9" fill="#4a3328" />
        <circle cx="36.5" cy="62" r="1.8" fill={tint} />
        <circle cx="83.5" cy="62" r="1.8" fill={tint} />
      </>
    ),
    badge: (tint) => (
      <g>
        <rect x="87" y="86" width="14" height="19" rx="3" fill={tint} />
        <rect x="89.5" y="88.5" width="9" height="4" rx="1" fill="#fff" fillOpacity="0.9" />
        {[0, 1].map((r) =>
          [0, 1, 2].map((c) => (
            <rect
              key={`${r}${c}`}
              x={89.5 + c * 3.4}
              y={95 + r * 4.2}
              width="2.4"
              height="2.4"
              rx="0.6"
              fill="#fff"
              fillOpacity="0.75"
            />
          )),
        )}
      </g>
    ),
  },
  // Sona: a high ponytail and a hairband; a small table with a tick.
  tables: {
    skin: "#efcdb2",
    hair: "#3b2b22",
    front: LONG_FRONT,
    extra: (tint) => (
      <>
        <path d="M78 30c9-3 15 2 15 10 0 9-4 15-9 19 1-8-1-15-6-19z" fill="#3b2b22" />
        <path d="M38 43c6-11 15-16 24-16s18 5 22 14" fill="none" stroke={tint} strokeWidth="3.2" strokeLinecap="round" />
      </>
    ),
    badge: (tint) => (
      <g>
        <rect x="84" y="87" width="20" height="17" rx="2.5" fill={tint} />
        <path d="M84 92.5h20M84 98h20M91 87v17" stroke="#fff" strokeOpacity="0.7" strokeWidth="1.1" />
        <circle cx="101" cy="85.5" r="4.6" fill="#fff" stroke={tint} strokeWidth="1.2" />
        <path
          d="m98.8 85.6 1.5 1.5 2.9-3"
          fill="none"
          stroke={tint}
          strokeWidth="1.4"
          strokeLinecap="round"
          strokeLinejoin="round"
        />
      </g>
    ),
  },
  // Lilit: long hair; a pen with a spark.
  content: {
    skin: "#e3b896",
    hair: "#3a2a24",
    back: LONG_BACK,
    front: LONG_FRONT,
    extra: (tint) => <circle cx="36" cy="63" r="2" fill={tint} />,
    badge: (tint) => (
      <g>
        <path d="m87 103 2-6 10-10 4 4-10 10z" fill={tint} />
        <path d="m87 103 2-6 4 4z" fill="#202623" fillOpacity="0.5" />
        <path d="M100 85q.8 2.6 3.4 3.4-2.6.8-3.4 3.4-.8-2.6-3.4-3.4 2.6-.8 3.4-3.4z" fill={tint} fillOpacity="0.6" />
      </g>
    ),
  },
  // Davit: a cap worn backwards; a phone playing a reel.
  reels: {
    skin: "#d9a784",
    hair: "#231c19",
    front: SHORT,
    extra: (tint) => (
      <>
        <path d="M37 44c2-12 12-19 24-19s21 7 23 19c-14-5-32-5-47 0z" fill={tint} />
        <path d="M80 41c5 1 9 3 11 6-4 1-8 0-11-2z" fill={tint} />
      </>
    ),
    badge: (tint) => (
      <g>
        <rect x="88" y="85" width="12" height="21" rx="3" fill={tint} />
        <path d="m92 91.5 5 3.5-5 3.5z" fill="#fff" />
      </g>
    ),
  },
  // Narek: headphones; a strip of film.
  editor: {
    skin: "#f0cfb5",
    hair: "#5a4033",
    front: SHORT,
    extra: (tint) => (
      <>
        <path d="M34 54c0-20 12-31 26-31s26 11 26 31" fill="none" stroke={tint} strokeWidth="4" strokeLinecap="round" />
        <rect x="30" y="49" width="9" height="15" rx="4" fill={tint} />
        <rect x="81" y="49" width="9" height="15" rx="4" fill={tint} />
      </>
    ),
    badge: (tint) => (
      <g>
        <rect x="84" y="89" width="20" height="13" rx="2" fill={tint} />
        {[0, 1, 2, 3].map((i) => (
          <g key={i} fill="#fff" fillOpacity="0.85">
            <rect x={85.5 + i * 4.8} y="90.3" width="2.4" height="1.8" rx="0.5" />
            <rect x={85.5 + i * 4.8} y="98.9" width="2.4" height="1.8" rx="0.5" />
          </g>
        ))}
        <rect x="88" y="93.5" width="12" height="4" rx="1" fill="#fff" fillOpacity="0.45" />
      </g>
    ),
  },
  // Mariam: long hair and a beret; a camera.
  designer: {
    skin: "#ebc7ab",
    hair: "#2b211d",
    back: LONG_BACK,
    front: LONG_FRONT,
    extra: (tint) => (
      <>
        <ellipse cx="55" cy="28" rx="23" ry="8" fill={tint} transform="rotate(-10 55 28)" />
        <circle cx="52" cy="19.5" r="2.2" fill={tint} />
      </>
    ),
    badge: (tint) => (
      <g>
        <rect x="84" y="89" width="20" height="14" rx="3.5" fill={tint} />
        <rect x="89" y="86.5" width="7" height="3.5" rx="1.2" fill={tint} />
        <circle cx="94" cy="96" r="4.2" fill="#fff" fillOpacity="0.9" />
        <circle cx="94" cy="96" r="2.2" fill={tint} />
      </g>
    ),
  },
};

const FALLBACK_LOOK: Look = {
  skin: "#e9c4a6",
  hair: "#3a2a24",
  front: SHORT,
  badge: (tint) => <circle cx="94" cy="96" r="5" fill={tint} />,
};

/**
 * An agent drawn as a small person in its colour: it breathes, blinks and
 * waves its tool now and then; it talks while it writes an answer. An agent
 * the business doesn't have is grey and still.
 */
export function AgentAvatar({
  agentKey,
  size = 72,
  active = true,
  talking = false,
  delay = 0,
  className,
  title,
}: {
  agentKey: string;
  size?: number;
  active?: boolean;
  talking?: boolean;
  /** Seconds: avatars side by side don't move in step. */
  delay?: number;
  className?: string;
  title?: string;
}) {
  const { tint } = agentTint(agentKey);
  const look = LOOKS[agentKey] ?? FALLBACK_LOOK;
  const d = { "--d": `${delay}s` } as CSSProperties;

  return (
    <svg
      viewBox="0 0 120 120"
      width={size}
      height={size}
      className={cn(styles.avatar, !active && styles.off, className)}
      style={d}
      role={title ? "img" : "presentation"}
      aria-hidden={title ? undefined : true}
      aria-label={title}
    >
      <ellipse className={styles.shadow} cx="60" cy="115" rx="26" ry="3.5" fill="#202623" />
      <g className={styles.bob}>
        {look.back ? <path d={look.back} fill={look.hair} /> : null}
        <rect x="53.5" y="70" width="13" height="18" rx="5" fill={look.skin} />
        <path d="M24 118c0-21 15-31 36-31s36 10 36 31z" fill={tint} />
        <path d="M49 88l11 11 11-11" fill="#fff" fillOpacity="0.85" />
        <circle cx="36" cy="55" r="4.5" fill={look.skin} />
        <circle cx="84" cy="55" r="4.5" fill={look.skin} />
        <circle cx="60" cy="52" r="24" fill={look.skin} />
        <path d={look.front} fill={look.hair} />
        <ellipse className={styles.eye} cx="50.5" cy="54" rx="2.6" ry="3.3" fill="#202623" />
        <ellipse className={styles.eye} cx="69.5" cy="54" rx="2.6" ry="3.3" fill="#202623" />
        <circle cx="45" cy="62" r="3.6" fill="#e07a6e" fillOpacity="0.22" />
        <circle cx="75" cy="62" r="3.6" fill="#e07a6e" fillOpacity="0.22" />
        {talking ? (
          <ellipse className={styles.mouthTalk} cx="60" cy="65" rx="4.2" ry="3" fill="#7a3e33" />
        ) : (
          <path d="M54 63.5q6 5 12 0" fill="none" stroke="#7a3e33" strokeWidth="2" strokeLinecap="round" />
        )}
        {look.extra?.(tint)}
      </g>
      <g className={styles.badge}>
        <circle cx="94" cy="96" r="15" fill="#fff" stroke={tint} strokeOpacity="0.3" />
        {look.badge(tint)}
      </g>
    </svg>
  );
}

/** The general AI assistant: a calm green orb with a spark, next to the agents. */
export function AssistantOrb({ size = 72, className }: { size?: number; className?: string }) {
  // Its own gradient id: a page may draw several orbs, some of them hidden.
  const gradient = `phay-orb-${useId().replace(/:/g, "")}`;
  return (
    <svg viewBox="0 0 120 120" width={size} height={size} className={cn(styles.avatar, className)} aria-hidden="true">
      <defs>
        <radialGradient id={gradient} cx="38%" cy="32%" r="70%">
          <stop offset="0%" stopColor="#8fb5a8" />
          <stop offset="55%" stopColor="#3f6f62" />
          <stop offset="100%" stopColor="#2b4f45" />
        </radialGradient>
      </defs>
      <ellipse className={styles.shadow} cx="60" cy="112" rx="24" ry="3.5" fill="#202623" />
      <g className={styles.bob}>
        <circle cx="60" cy="58" r="34" fill={`url(#${gradient})`} />
        <ellipse cx="48" cy="42" rx="11" ry="6" fill="#fff" fillOpacity="0.28" transform="rotate(-25 48 42)" />
        <g className={styles.orb} style={{ transformOrigin: "60px 58px" }}>
          <path d="M60 40q3 13 16 18-13 4-16 18-3-14-16-18 13-5 16-18z" fill="#fff" fillOpacity="0.95" />
          <path d="M79 34q1.2 4.5 5.5 5.6-4.3 1.2-5.5 5.6-1.2-4.4-5.5-5.6 4.3-1.1 5.5-5.6z" fill="#fff" fillOpacity="0.7" />
        </g>
      </g>
    </svg>
  );
}
