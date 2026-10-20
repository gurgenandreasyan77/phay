import type { CSSProperties, ReactElement } from "react";
import styles from "./subscription.module.css";

/** Each module's colour: its accent, a soft wash and the card's hover glow. */
export type ModuleTint = { tint: string; soft: string; bg: string };

export const MODULE_TINTS: Record<string, ModuleTint> = {
  services: { tint: "#3f6f62", soft: "rgba(63, 111, 98, 0.18)", bg: "#eaf2ee" },
  market: { tint: "#b47a2b", soft: "rgba(185, 130, 50, 0.2)", bg: "#f8efe0" },
  site: { tint: "#3f6b86", soft: "rgba(82, 117, 137, 0.2)", bg: "#e7eff4" },
};

const FALLBACK_TINT: ModuleTint = { tint: "#5d6a64", soft: "rgba(93, 106, 100, 0.16)", bg: "#eef0ee" };

export function moduleTint(key: string): ModuleTint {
  return MODULE_TINTS[key] ?? FALLBACK_TINT;
}

/** The CSS variables a module card reads its colours from. */
export function tintStyle(key: string): CSSProperties {
  const t = moduleTint(key);
  return {
    "--tint": t.tint,
    "--tint-soft": t.soft,
    "--tint-bg": t.bg,
    "--tint-border": t.soft,
    "--tint-shadow": t.soft.replace(/[\d.]+\)$/, "0.9)"),
  } as CSSProperties;
}

const delay = (d: number) => ({ "--d": `${d}s` }) as CSSProperties;

/** A small picture of what the module does, drawn in its colour. */
export function ModuleArt({ moduleKey }: { moduleKey: string }) {
  const { tint } = moduleTint(moduleKey);
  const Art = ARTS[moduleKey] ?? GenericArt;
  return (
    <div className={`${styles.art} h-32`}>
      <svg viewBox="0 0 280 128" className="h-full w-full" role="presentation" aria-hidden="true">
        <Art tint={tint} />
      </svg>
    </div>
  );
}

type ArtProps = { tint: string };

/** Services: a price list with a calculator next to it. */
function ServicesArt({ tint }: ArtProps) {
  return (
    <>
      <g className={styles.float} style={delay(0)}>
        <rect x="44" y="18" width="128" height="94" rx="10" fill="#fff" stroke={tint} strokeOpacity="0.18" />
        {[0, 1, 2].map((row) => (
          <g key={row} transform={`translate(56 ${32 + row * 26})`}>
            <rect width="16" height="16" rx="5" fill={tint} fillOpacity={0.18 + row * 0.1} />
            <path d="M4.5 8.5 7 11l4.5-5" stroke={tint} strokeWidth="1.8" fill="none" strokeLinecap="round" />
            <rect x="24" y="2" width={46 - row * 8} height="5" rx="2.5" fill="#202623" fillOpacity="0.55" />
            <rect x="24" y="10" width={30 + row * 6} height="4" rx="2" fill="#202623" fillOpacity="0.2" />
            <rect x="80" y="2" width="24" height="12" rx="6" fill={tint} fillOpacity="0.9" />
          </g>
        ))}
      </g>
      <g className={styles.float} style={delay(0.8)}>
        <rect x="184" y="34" width="58" height="76" rx="10" fill={tint} />
        <rect x="192" y="42" width="42" height="16" rx="4" fill="#fff" fillOpacity="0.9" />
        <rect x="214" y="47" width="15" height="6" rx="2" fill={tint} fillOpacity="0.7" />
        {[0, 1, 2].map((r) =>
          [0, 1, 2].map((c) => (
            <rect
              key={`${r}${c}`}
              x={192 + c * 15}
              y={64 + r * 14}
              width="11"
              height="10"
              rx="3"
              fill="#fff"
              fillOpacity={r === 2 && c === 2 ? 0.95 : 0.35}
            />
          )),
        )}
      </g>
      <g className={styles.float} style={delay(1.6)}>
        <circle cx="36" cy="96" r="13" fill="#fff" stroke={tint} strokeOpacity="0.25" />
        <path d="M30 96h12M36 90v12" stroke={tint} strokeWidth="2.2" strokeLinecap="round" />
      </g>
      <Spark x={246} y={24} tint={tint} d={0.4} />
      <Spark x={30} y={30} tint={tint} d={1.4} />
    </>
  );
}

/** Market: a shop window with products and a bag. */
function MarketArt({ tint }: ArtProps) {
  return (
    <>
      <g className={styles.float} style={delay(0)}>
        <rect x="40" y="26" width="150" height="88" rx="10" fill="#fff" stroke={tint} strokeOpacity="0.2" />
        <path
          d="M40 36a10 10 0 0 1 10-10h130a10 10 0 0 1 10 10v8H40z"
          fill={tint}
          fillOpacity="0.9"
        />
        {[0, 1, 2, 3, 4].map((i) => (
          <path key={i} d={`M${46 + i * 29} 44q7 10 15 0`} fill={i % 2 ? "#fff" : tint} fillOpacity={i % 2 ? 0.9 : 0.55} />
        ))}
        {[0, 1, 2].map((i) => (
          <g key={i} transform={`translate(${52 + i * 44} 60)`}>
            <rect width="36" height="44" rx="6" fill={tint} fillOpacity="0.1" />
            <rect x="8" y="6" width="20" height="18" rx="5" fill={tint} fillOpacity={0.35 + i * 0.2} />
            <rect x="6" y="30" width="24" height="4" rx="2" fill="#202623" fillOpacity="0.4" />
            <rect x="6" y="37" width="14" height="4" rx="2" fill={tint} />
          </g>
        ))}
      </g>
      <g className={styles.float} style={delay(0.9)}>
        <path d="M206 58h44l-5 52h-34z" fill={tint} />
        <path d="M218 58v-6a10 10 0 0 1 20 0v6" stroke={tint} strokeWidth="3" fill="none" />
        <circle cx="228" cy="82" r="9" fill="#fff" fillOpacity="0.9" />
        <path d="m224 82 3 3 5-6" stroke={tint} strokeWidth="2" fill="none" strokeLinecap="round" />
      </g>
      <g className={styles.float} style={delay(1.7)}>
        <rect x="18" y="86" width="34" height="18" rx="9" fill="#fff" stroke={tint} strokeOpacity="0.3" />
        <rect x="25" y="92" width="20" height="6" rx="3" fill={tint} fillOpacity="0.7" />
      </g>
      <Spark x={250} y={30} tint={tint} d={0.2} />
      <Spark x={24} y={22} tint={tint} d={1.2} />
    </>
  );
}

/** Site: a browser with a hero, a video and blog lines. */
function SiteArt({ tint }: ArtProps) {
  return (
    <>
      <g className={styles.float} style={delay(0)}>
        <rect x="46" y="16" width="160" height="100" rx="10" fill="#fff" stroke={tint} strokeOpacity="0.2" />
        <path d="M46 26a10 10 0 0 1 10-10h140a10 10 0 0 1 10 10v4H46z" fill={tint} fillOpacity="0.14" />
        {[0, 1, 2].map((i) => (
          <circle key={i} cx={56 + i * 8} cy="23" r="2.4" fill={tint} fillOpacity={0.4 + i * 0.2} />
        ))}
        <rect x="82" y="20" width="80" height="6" rx="3" fill="#fff" />
        <rect x="56" y="38" width="88" height="40" rx="6" fill={tint} fillOpacity="0.85" />
        <rect x="64" y="48" width="44" height="6" rx="3" fill="#fff" fillOpacity="0.9" />
        <rect x="64" y="58" width="30" height="4" rx="2" fill="#fff" fillOpacity="0.6" />
        <rect x="64" y="66" width="22" height="7" rx="3.5" fill="#fff" />
        <rect x="152" y="38" width="44" height="40" rx="6" fill={tint} fillOpacity="0.15" />
        <circle cx="174" cy="58" r="10" fill={tint} />
        <path d="m171 53 8 5-8 5z" fill="#fff" />
        {[0, 1].map((i) => (
          <g key={i} transform={`translate(${56 + i * 72} 86)`}>
            <rect width="18" height="18" rx="4" fill={tint} fillOpacity="0.25" />
            <rect x="24" y="3" width="40" height="4" rx="2" fill="#202623" fillOpacity="0.45" />
            <rect x="24" y="11" width="28" height="4" rx="2" fill="#202623" fillOpacity="0.2" />
          </g>
        ))}
      </g>
      <g className={styles.float} style={delay(1)}>
        <rect x="214" y="58" width="44" height="34" rx="9" fill={tint} />
        <path d="M222 92v8l8-8" fill={tint} />
        <rect x="222" y="67" width="28" height="4" rx="2" fill="#fff" fillOpacity="0.9" />
        <rect x="222" y="76" width="18" height="4" rx="2" fill="#fff" fillOpacity="0.6" />
      </g>
      <g className={styles.float} style={delay(1.8)}>
        <circle cx="30" cy="70" r="14" fill="#fff" stroke={tint} strokeOpacity="0.25" />
        <circle cx="30" cy="70" r="7" fill="none" stroke={tint} strokeWidth="2" />
        <path d="M23 70h14M30 63c3 4 3 10 0 14M30 63c-3 4-3 10 0 14" stroke={tint} strokeWidth="1.4" fill="none" />
      </g>
      <Spark x={240} y={28} tint={tint} d={0.5} />
    </>
  );
}

function GenericArt({ tint }: ArtProps) {
  return (
    <g className={styles.float}>
      <rect x="100" y="24" width="80" height="80" rx="16" fill={tint} fillOpacity="0.85" />
      <path d="M126 64h28M140 50v28" stroke="#fff" strokeWidth="5" strokeLinecap="round" />
      <Spark x={196} y={30} tint={tint} d={0.3} />
    </g>
  );
}

function Spark({ x, y, tint, d }: { x: number; y: number; tint: string; d: number }) {
  return (
    <path
      className={styles.float}
      style={delay(d)}
      d={`M${x} ${y - 7}q1.5 5.5 7 7q-5.5 1.5-7 7q-1.5-5.5-7-7q5.5-1.5 7-7z`}
      fill={tint}
      fillOpacity="0.55"
    />
  );
}

const ARTS: Record<string, (props: ArtProps) => ReactElement> = {
  services: ServicesArt,
  market: MarketArt,
  site: SiteArt,
};
