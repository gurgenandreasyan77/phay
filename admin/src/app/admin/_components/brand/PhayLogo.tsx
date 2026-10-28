import { useId } from "react";
import { cn } from "@/ui";

/*
 * The Phay logo, drawn in code so it stays sharp at any size and needs no
 * request. The mark is a «p» whose bowl is three agents (three arcs) around
 * the owner (the gold dot) and two teammates. Colors are the brand's
 * "Lagoon" palette; the downloadable files and the brand guide use the same
 * geometry.
 */

type Tone = "color" | "onDark" | "white";

const TONES: Record<Tone, { from: string; to: string; owner: string; team: string; word: string; fades: [number, number] }> = {
  color: { from: "#0F4C5C", to: "#5FD0B0", owner: "#F2B544", team: "#0F4C5C", word: "#0C3540", fades: [1, 1] },
  onDark: { from: "#2A8CA0", to: "#8EE6CB", owner: "#F2B544", team: "#8EE6CB", word: "#E4F4EF", fades: [1, 1] },
  white: { from: "#FFFFFF", to: "#FFFFFF", owner: "#F2B544", team: "#FFFFFF", word: "#FFFFFF", fades: [0.78, 0.56] },
};

const ARCS = ["M24.53 40.40A17 17 0 0 1 24.53 13.60", "M29.19 11.03A17 17 0 0 1 51.74 24.05", "M51.83 29.37A17 17 0 0 1 29.19 42.97"] as const;

const WORDMARK = "M326 10Q274 10 232 -11.5Q190 -33 165 -70Q140 -107 138 -155V-326Q140 -374 165.5 -411.5Q191 -449 232.5 -471Q274 -493 326 -493Q393 -493 446 -460Q499 -427 529.5 -370Q560 -313 560 -241Q560 -170 529.5 -113Q499 -56 446 -23Q393 10 326 10ZM58 202V-483H189V-356L167 -239L188 -122V202ZM303 -111Q339 -111 367 -127.5Q395 -144 410.5 -173.5Q426 -203 426 -242Q426 -280 410.5 -309.5Q395 -339 367 -355.5Q339 -372 303 -372Q268 -372 240 -355.5Q212 -339 196.5 -309.5Q181 -280 181 -242Q181 -203 196.5 -173.5Q212 -144 239.5 -127.5Q267 -111 303 -111ZM936 0V-277Q936 -320 909 -347Q882 -374 839 -374Q811 -374 789 -362Q767 -350 754.5 -328Q742 -306 742 -277L691 -303Q691 -360 715 -402.5Q739 -445 781.5 -469Q824 -493 879 -493Q934 -493 976.5 -469Q1019 -445 1043 -403Q1067 -361 1067 -306V0ZM611 0V-723H742V0ZM1340 10Q1274 10 1221 -23Q1168 -56 1138 -113Q1108 -170 1108 -241Q1108 -313 1138 -370Q1168 -427 1221 -460Q1274 -493 1340 -493Q1392 -493 1433 -472Q1474 -451 1498.5 -413.5Q1523 -376 1525 -329V-155Q1523 -107 1498.5 -70Q1474 -33 1433 -11.5Q1392 10 1340 10ZM1364 -111Q1419 -111 1453 -147.5Q1487 -184 1487 -242Q1487 -280 1471.5 -309.5Q1456 -339 1428.5 -355.5Q1401 -372 1364 -372Q1328 -372 1300.5 -355.5Q1273 -339 1257.5 -309.5Q1242 -280 1242 -242Q1242 -203 1257.5 -173.5Q1273 -144 1300.5 -127.5Q1328 -111 1364 -111ZM1480 0V-130L1501 -248L1480 -364V-483H1610V0ZM1843 4 1641 -483H1783L1917 -111H1869L2008 -483H2151L1936 4ZM1709 202 1856 -109 1936 4 1848 202Z";

function MarkShapes({ tone, gradientId }: { tone: Tone; gradientId: string }) {
  const t = TONES[tone];
  const paint = `url(#${gradientId})`;
  return (
    <>
      <defs>
        <linearGradient id={gradientId} gradientUnits="userSpaceOnUse" x1="14" y1="60" x2="54" y2="8">
          <stop offset="0" stopColor={t.from} />
          <stop offset="1" stopColor={t.to} />
        </linearGradient>
      </defs>
      <path d="M18 27V58" fill="none" stroke={paint} strokeWidth="8" strokeLinecap="round" />
      <path d={ARCS[0]} fill="none" stroke={paint} strokeWidth="8" />
      <path d={ARCS[1]} fill="none" stroke={paint} strokeWidth="8" opacity={t.fades[0]} />
      <path d={ARCS[2]} fill="none" stroke={paint} strokeWidth="8" opacity={t.fades[1]} />
      <circle cx="35" cy="22.4" r="3" fill={t.owner} />
      <circle cx="30.8" cy="29.6" r="3" fill={t.team} />
      <circle cx="39.2" cy="29.6" r="3" fill={t.team} />
    </>
  );
}

/** The mark alone (sidebar, favicon-sized spots). Height comes from className, e.g. "h-8". */
export function PhayMark({ tone = "color", className }: { tone?: Tone; className?: string }) {
  // Each copy gets its own gradient id: a shared one breaks when the first copy is hidden.
  const gradientId = useId();
  return (
    <svg viewBox="12 4 46 60" className={cn("w-auto shrink-0", className)} role="img" aria-label="Phay">
      <MarkShapes tone={tone} gradientId={gradientId} />
    </svg>
  );
}

/** Mark and the word «phay» (sign-in pages, error pages). Height comes from className, e.g. "h-10". */
export function PhayLogo({ tone = "color", className }: { tone?: Tone; className?: string }) {
  const gradientId = useId();
  return (
    <svg viewBox="12 4 151.3 60" className={cn("w-auto shrink-0", className)} role="img" aria-label="Phay">
      <MarkShapes tone={tone} gradientId={gradientId} />
      <path transform="translate(62.33 47.5) scale(0.0460)" d={WORDMARK} fill={TONES[tone].word} />
    </svg>
  );
}
