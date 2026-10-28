"use client";

import Link from "next/link";
import { useState, type CSSProperties } from "react";
import { useRouter } from "next/navigation";
import { ArrowRight, Bot, CalendarClock, MessageCircle, RefreshCw, Wallet } from "lucide-react";
import type { BalanceDto } from "@/lib/adminApi";
import { cn } from "@/ui";
import { amd, formatDay } from "../subscription/shared";
import { useCountUp } from "../agents/AgentTermRing";
import styles from "./balance.module.css";

/**
 * The business's balance as a deep lagoon card: the amount counting up,
 * how many months of everything it pays it holds, the payments coming with
 * a row of stones lit as far as the balance carries them, the switch for
 * renewing on its own, and how to top it up (a receipt in the chat).
 * `history` links to the ledger on the subscription page (the home page).
 */
export function BalanceCard({ balance, history = false }: { balance: BalanceDto; history?: boolean }) {
  const router = useRouter();
  const shown = useCountUp(balance.amount, 1200);
  const [autoRenew, setAutoRenew] = useState(balance.autoRenew);
  const [saving, setSaving] = useState(false);

  const auto = balance.upcoming.filter((item) => item.auto);
  const next = balance.upcoming[0] ?? null;
  // The next payment that renews on its own and the balance doesn't carry.
  const short = autoRenew ? auto.find((item) => !item.covered) : undefined;
  const low = Boolean(short) && short === auto[0];

  async function toggle() {
    const value = !autoRenew;
    setAutoRenew(value);
    setSaving(true);
    const response = await fetch("/api/admin/balance", {
      method: "PATCH",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ auto_renew: value }),
    }).catch(() => null);
    setSaving(false);
    if (!response?.ok) {
      setAutoRenew(!value);
      return;
    }
    router.refresh();
  }

  return (
    <section className={cn(styles.hero, low && styles.low, "p-5 sm:p-6")} aria-label="Հաշվեկշիռ">
      <Waves />

      <div className="grid gap-6 lg:grid-cols-[1fr_1.1fr] lg:items-start">
        <div className="space-y-4">
          <div className="flex items-center gap-2 text-sm font-medium">
            <span className={cn(styles.glass, "flex h-8 w-8 items-center justify-center rounded-full")}>
              <Wallet className="h-4 w-4" />
            </span>
            Հաշվեկշիռ
          </div>

          <div>
            <p className={cn(styles.amount, "text-4xl font-semibold sm:text-5xl")}>{amd(shown)}</p>
            <p className={cn(styles.muted, "mt-2 text-sm")}>
              {balance.monthly > 0
                ? balance.months && balance.months > 0
                  ? `Բավական է ${balance.months} ամսվա՝ ${amd(balance.monthly)}/ամիս`
                  : `Ամսական վճարը՝ ${amd(balance.monthly)}`
                : "Կարող եք վճարել բաժանորդագրության, մոդուլների և AI աշխատակիցների համար"}
            </p>
          </div>

          <button
            type="button"
            onClick={toggle}
            disabled={saving}
            aria-pressed={autoRenew}
            className={cn(
              styles.glass,
              "flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-left disabled:opacity-70 sm:w-auto",
            )}
          >
            <RefreshCw className="h-4 w-4 shrink-0" />
            <span className="min-w-0 flex-1 text-sm">
              <span className="block font-medium">Ինքնաշխատ երկարացում</span>
              <span className={cn(styles.muted, "block text-xs")}>
                {autoRenew ? "Ժամկետի ավարտին գումարը կգանձվի հաշվեկշռից" : "Անջատված է. երկարացնում եք ձեռքով"}
              </span>
            </span>
            <span className={cn(styles.switch, autoRenew && styles.switchOn)} aria-hidden />
          </button>
        </div>

        <div className={cn(styles.glass, "space-y-3 rounded-2xl p-4")}>
          <div className="flex items-baseline justify-between gap-3">
            <p className="text-sm font-medium">Առաջիկա վճարումներ</p>
            {balance.coversUntil && autoRenew ? (
              <p className={cn(styles.muted, "text-xs")}>ծածկված է մինչև {formatDay(balance.coversUntil)}</p>
            ) : null}
          </div>

          {balance.upcoming.length > 0 ? (
            <>
              <div className={styles.stones} aria-hidden>
                {balance.upcoming.map((item, index) => (
                  <span
                    key={`${item.kind}-${item.key}`}
                    className={cn(
                      styles.stone,
                      item.covered ? styles.stoneOn : item.auto && autoRenew ? styles.stoneShort : null,
                    )}
                    style={{ "--i": index } as CSSProperties}
                  />
                ))}
              </div>
              <ul className="space-y-2">
                {balance.upcoming.slice(0, 4).map((item, index) => (
                  <li
                    key={`${item.kind}-${item.key}`}
                    className={cn(styles.item, "flex items-center gap-3 text-sm")}
                    style={{ "--i": index } as CSSProperties}
                  >
                    {item.kind === "agent" ? (
                      <Bot className="h-4 w-4 shrink-0 opacity-80" />
                    ) : (
                      <CalendarClock className="h-4 w-4 shrink-0 opacity-80" />
                    )}
                    <span className="min-w-0 flex-1 truncate">
                      {item.kind === "agent" ? `AI · ${item.label}` : item.label}
                      <span className={cn(styles.muted, "ml-2 text-xs")}>{formatDay(item.date)}</span>
                    </span>
                    <span className="shrink-0 font-medium tabular-nums">{amd(item.amount)}</span>
                    <span
                      className={styles.dot}
                      title={item.covered ? "Կգանձվի հաշվեկշռից" : item.auto ? "Հաշվեկշիռը բավարար չէ" : "Վճարվում է ձեռքով"}
                      style={{
                        background: item.covered ? "#7fd8bf" : item.auto && autoRenew ? "#f0be6e" : "rgba(255,255,255,0.3)",
                      }}
                    />
                  </li>
                ))}
              </ul>
            </>
          ) : (
            <p className={cn(styles.muted, "text-sm")}>Վճարելու բան չկա։</p>
          )}

          {short ? (
            <p className="rounded-lg bg-[rgba(240,190,110,0.18)] px-3 py-2 text-xs text-[#ffe2b0]">
              {next === short ? "Հաջորդ" : "Մի"} վճարման համար ({short.label}, {formatDay(short.date)}) պակասում է հաշվեկշիռը։
              Համալրեք, որ ոչինչ չընդհատվի։
            </p>
          ) : null}
        </div>
      </div>

      <div className="mt-5 flex flex-wrap items-center gap-x-5 gap-y-2 border-t border-white/10 pt-4 text-sm">
        <Link
          href="/admin/support"
          className="inline-flex items-center gap-2 rounded-full bg-white px-4 py-2 font-medium text-[#0b2e36] shadow-sm transition hover:bg-[#e0f4ee]"
        >
          <MessageCircle className="h-4 w-4" />
          Համալրել
        </Link>
        <span className={cn(styles.muted, "min-w-0 flex-1 basis-56 text-xs")}>
          Վճարեք և ուղարկեք անդորրագիրը չատում. Phay-ի թիմը կհամալրի հաշվեկշիռը։
        </span>
        {history ? (
          <Link
            href="/admin/subscription#balance"
            className="inline-flex items-center gap-1 font-medium text-white hover:underline"
          >
            Պատմություն <ArrowRight className="h-4 w-4" />
          </Link>
        ) : null}
      </div>
    </section>
  );
}

function Waves() {
  const path = "M0 40 C 150 10 350 70 500 40 C 650 10 850 70 1000 40 L1000 100 L0 100 Z";
  return (
    <div className={styles.waves} aria-hidden>
      <svg className={styles.wave} viewBox="0 0 2000 100" preserveAspectRatio="none">
        <path d={path} transform="translate(0 0)" fill="rgba(127,216,191,0.10)" />
        <path d={path} transform="translate(1000 0)" fill="rgba(127,216,191,0.10)" />
      </svg>
      <svg className={cn(styles.wave, styles.wave2)} viewBox="0 0 2000 100" preserveAspectRatio="none">
        <path d={path} transform="translate(0 18)" fill="rgba(255,255,255,0.06)" />
        <path d={path} transform="translate(1000 18)" fill="rgba(255,255,255,0.06)" />
      </svg>
    </div>
  );
}
