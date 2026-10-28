"use client";

import { useMemo, useSyncExternalStore, type CSSProperties } from "react";
import { cn } from "@/ui";
import { AgentAvatar, agentTintStyle } from "./AgentAvatar";
import { shortDay } from "./terms";
import styles from "./terms.module.css";

const DAY = 86_400_000;
/** Faces closer than this (% of the line) stand on taller stems, one above the other. */
const NEAR = 9;

// When the page opened in the browser: the line's today (the server renders none, so both agree).
let openedAt: number | null = null;
const noop = () => () => {};

export type TimelineItem = { key: string; name: string; endsAt: string | null; moving?: boolean };

/**
 * Every agent's end day on one line from today: a face on a stem at its
 * day (ended ones wait at today in red). With a common day (`target`), the
 * moving agents slide to it and a dashed line marks the billing day.
 */
export function TermTimeline({ items, target, avatar = 34 }: { items: TimelineItem[]; target?: string | null; avatar?: number }) {
  const now = useSyncExternalStore(
    noop,
    () => (openedAt ??= Date.now()),
    () => null,
  );

  const layout = useMemo(() => {
    const start = now ?? 0;
    const ends = items.map((item) => (item.endsAt ? Date.parse(item.endsAt) : start));
    const targetAt = target ? Date.parse(target) : null;
    const last = Math.max(start + 14 * DAY, ...ends, targetAt ?? 0);
    const span = (last - start) * 1.12 || 1;
    const x = (t: number) => Math.min(100, Math.max(0, ((t - start) / span) * 100));

    const placed = items
      .map((item, index) => {
        const at = item.moving && targetAt ? targetAt : ends[index];
        return { ...item, order: index, x: x(at), late: !item.moving && at <= start };
      })
      .sort((a, b) => a.x - b.x);

    // Lanes: a face too near the one before it climbs a lane.
    const lanes: number[] = [];
    const withLanes = placed.map((item) => {
      let lane = lanes.findIndex((lastX) => item.x - lastX >= NEAR);
      if (lane === -1) lane = lanes.length;
      lanes[lane] = item.x;
      return { ...item, lane };
    });

    return {
      items: withLanes,
      lanes: Math.max(1, lanes.length),
      target: targetAt ? x(targetAt) : null,
      middle: start + span / 2,
      end: start + span,
    };
  }, [items, target, now]);

  if (now === null) {
    return <div className={styles.timeline} style={{ "--h": "112px" } as CSSProperties} aria-hidden="true" />;
  }

  const height = 70 + avatar + (layout.lanes - 1) * 30;

  return (
    <div
      className={styles.timeline}
      style={{ "--h": `${height}px` } as CSSProperties}
      role="img"
      aria-label="AI աշխատակիցների ավարտի օրերը"
    >
      <div className={styles.axis}>
        <div className={styles.axisGlow} />
      </div>
      <span className={styles.today} />
      <span className={styles.tick} style={{ left: 0 }}>
        Այսօր
      </span>
      <span className={styles.tick} style={{ left: "50%" }}>
        {shortDay(new Date(layout.middle).toISOString())}
      </span>
      <span className={styles.tick} style={{ left: "100%" }}>
        {shortDay(new Date(layout.end).toISOString())}
      </span>

      {layout.target !== null ? (
        <div
          className={cn(styles.target, layout.target > 70 && styles.targetLeft)}
          style={{ "--x": `${layout.target}%` } as CSSProperties}
        >
          <span className={styles.targetLabel}>Վճարման օր · {shortDay(target ?? null)}</span>
        </div>
      ) : null}

      {layout.items.map((item) => (
        <div
          key={item.key}
          className={cn(styles.marker, item.late && styles.markerLate)}
          style={
            { ...agentTintStyle(item.key), "--x": `${item.x}%`, "--lane": item.lane, "--order": item.order } as CSSProperties
          }
          title={`${item.name} · ${item.endsAt ? shortDay(item.endsAt) : "—"}`}
        >
          <span className={styles.markerFace}>
            <AgentAvatar agentKey={item.key} size={avatar} delay={-item.order * 0.7} active={!item.late} />
          </span>
          <span className={styles.markerStem} />
        </div>
      ))}
    </div>
  );
}
