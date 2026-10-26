import Link from "next/link";
import type { CSSProperties } from "react";
import { Lock } from "lucide-react";
import type { AgentDto } from "@/lib/adminApi";
import { cn } from "@/ui";
import { AgentAvatar, AssistantOrb, agentTintStyle } from "./AgentAvatar";
import styles from "./agents.module.css";

/** The pool keeps at least this much room for each swimmer, so they never cross. */
const LANE = 132;

/** Where each swimmer lives in the pool and how it moves, varied by its place. */
function place(index: number, count: number): CSSProperties {
  const step = 100 / count;
  const x = step * index + step / 2;
  return {
    "--x": `calc(${x}% - 52px)`,
    "--y": index % 2 === 0 ? "12%" : "20%",
    "--dx": `${index % 2 === 0 ? 8 : -8}px`,
    "--dy": `${4 + (index % 3) * 2}px`,
    "--t": `${13 + ((index * 5) % 7)}s`,
    "--d": `${-index * 2.3}s`,
  } as CSSProperties;
}

const BUBBLES = [
  { left: "8%", s: 7, t: 11, d: 0 },
  { left: "23%", s: 5, t: 9, d: 3 },
  { left: "41%", s: 9, t: 13, d: 6 },
  { left: "58%", s: 6, t: 10, d: 1.5 },
  { left: "74%", s: 8, t: 12, d: 4.5 },
  { left: "90%", s: 5, t: 9.5, d: 7 },
];

/**
 * The AI team on the assistant page: the general assistant and the agents
 * the business has swim in their pool (pick one to talk to it); the agents
 * it doesn't have yet wait greyed out below, a click shows what they do.
 */
export function AgentPool({
  agents,
  selected,
}: {
  agents: AgentDto[];
  /** The agent the chat is with; null: the general assistant. */
  selected: string | null;
}) {
  const active = agents.filter((a) => a.active);
  const waiting = agents.filter((a) => !a.active);
  const swimmers = [null, ...active];

  return (
    <section className={styles.pool} aria-label="Ձեր AI թիմը">
      {BUBBLES.map((b) => (
        <span
          key={b.left}
          className={styles.bubble}
          style={
            {
              left: b.left,
              "--s": `${b.s}px`,
              "--t": `${b.t}s`,
              "--d": `${b.d}s`,
            } as CSSProperties
          }
        />
      ))}

      <div className="flex items-start justify-between gap-3 px-4 pt-3 sm:px-5">
        <div>
          <p className="text-sm font-semibold text-ink">Ձեր AI թիմը</p>
          <p className="text-xs text-ink-muted">
            {active.length > 0
              ? "Ընտրեք, ում հետ եք ուզում խոսել։ Յուրաքանչյուրը սովորում է ձեր բիզնեսը։"
              : "Թիմում դեռ միայն ընդհանուր օգնականն է։ Ավելացրեք AI աշխատակիցներ ներքևից։"}
          </p>
        </div>
        <Link
          href="/admin/employees?tab=agents"
          className="-my-3 inline-flex min-h-11 shrink-0 items-center text-xs font-medium text-primary hover:underline"
        >
          Թիմը և ուսուցումը
        </Link>
      </div>

      {/* Phones and tablets: a grid of tiles, each easy to tap; the avatars still breathe. */}
      <ul className="grid grid-cols-[repeat(auto-fill,minmax(100px,1fr))] gap-2 px-3 pt-3 pb-4 sm:px-5 xl:hidden">
        {swimmers.map((agent, index) => {
          const key = agent?.key ?? null;
          const isSelected = selected === key;
          return (
            <li key={key ?? "assistant"}>
              <Link
                href={key ? `/admin/assistant?agent=${key}` : "/admin/assistant"}
                className={cn(
                  styles.tile,
                  "flex h-full min-h-11 flex-col items-center gap-1 rounded-xl border px-2 pt-2 pb-2.5 text-center outline-none",
                )}
                data-selected={isSelected}
                aria-current={isSelected ? "true" : undefined}
                style={agentTintStyle(key ?? "analyst")}
              >
                {agent ? <AgentAvatar agentKey={agent.key} size={60} delay={-index * 0.7} /> : <AssistantOrb size={60} />}
                <span className="text-sm leading-tight font-semibold text-ink">{agent ? agent.persona.hy : "Օգնական"}</span>
                <span className="line-clamp-2 text-[11px] leading-snug text-ink-muted">
                  {agent ? agent.role.hy : "Ընդհանուր հարցեր"}
                </span>
              </Link>
            </li>
          );
        })}
      </ul>

      {/* Wide screens: the team swims in its pool, each in a lane of its own. */}
      <div className="hidden overflow-x-auto overflow-y-hidden xl:block">
        <div className="relative h-52" style={{ minWidth: `${swimmers.length * LANE}px` }}>
          {swimmers.map((agent, index) => {
            const key = agent?.key ?? null;
            const isSelected = selected === key;
            const name = agent ? agent.persona.hy : "Օգնական";
            return (
              <Link
                key={key ?? "assistant"}
                href={key ? `/admin/assistant?agent=${key}` : "/admin/assistant"}
                className={cn(styles.swimmer, "flex w-[104px] flex-col items-center gap-1 rounded-xl outline-none")}
                data-selected={isSelected}
                aria-current={isSelected ? "true" : undefined}
                title={agent ? `${agent.label}. ${agent.tagline}` : "AI օգնական՝ ընդհանուր հարցերի համար"}
                style={{
                  ...place(index, swimmers.length),
                  ...agentTintStyle(key ?? "analyst"),
                }}
              >
                <span className={cn(styles.swimBody, "relative block")}>
                  <span className={styles.halo} />
                  {agent ? <AgentAvatar agentKey={agent.key} size={92} delay={-index * 0.7} /> : <AssistantOrb size={92} />}
                </span>
                <span className={styles.nameTag}>{name}</span>
              </Link>
            );
          })}
        </div>
      </div>

      {waiting.length > 0 ? (
        <div className="border-t border-border/70 bg-white/55 px-4 py-3 sm:px-5">
          <p className="mb-2 text-xs text-ink-muted">Կարող են միանալ ձեր թիմին</p>
          <div className="flex flex-wrap gap-2">
            {waiting.map((agent) => (
              <Link
                key={agent.key}
                href={`/admin/assistant?agent=${agent.key}`}
                className={cn(
                  "group flex min-h-11 items-center gap-2 rounded-full border border-border bg-surface py-1 pl-1 pr-3 text-xs text-ink-muted transition-colors hover:border-border-strong hover:text-ink",
                  selected === agent.key && "border-border-strong text-ink",
                )}
                title={agent.tagline}
              >
                <AgentAvatar agentKey={agent.key} size={34} active={false} />
                <span>
                  {agent.persona.hy} · {agent.role.hy}
                </span>
                <Lock className="h-3 w-3 opacity-60 group-hover:opacity-100" aria-hidden="true" />
              </Link>
            ))}
          </div>
        </div>
      ) : null}
    </section>
  );
}
