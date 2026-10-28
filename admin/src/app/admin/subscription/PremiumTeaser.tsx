"use client";

import { BarChart3, Bot, Crown, Lock, Puzzle } from "lucide-react";
import { useRef, useState, type CSSProperties, type PointerEvent } from "react";
import type { AssistantUsageDto } from "@/lib/adminApi";
import { formatNumber } from "../_components/tables/shared";
import { RequestButton } from "./RequestButton";
import styles from "./subscription.module.css";

/**
 * The premium plan before it exists: what it adds, and a blurred preview of
 * a richer analytics screen that shows through where the light falls. The
 * light wanders by itself and follows the pointer. Asking goes to the chat.
 */
export function PremiumTeaser({ assistant, canAsk }: { assistant: Pick<AssistantUsageDto, "used" | "limit">; canAsk: boolean }) {
  const limit = assistant.limit ?? 0;
  const used = Math.min(assistant.used, limit);
  const usedShare = limit > 0 ? used / limit : 0;

  const benefits = [
    {
      icon: BarChart3,
      title: "Ընդլայնված վերլուծություն",
      text: "Եկամտի կանխատեսում, ամիսների համեմատություն և ավելի մանրամասն հաշվետվություններ։",
    },
    {
      icon: Bot,
      title: "Ավելի շատ AI հարցումներ",
      text: `Հիմա՝ ամսական ${formatNumber(limit)} հարցում։ Պրեմիումում՝ զգալիորեն ավելի շատ։`,
    },
    {
      icon: Puzzle,
      title: "Ավելի շատ մոդուլներ",
      text: "Մոդուլների մի մասը կներառվի փաթեթի գնի մեջ՝ առանց հավելավճարի։",
    },
  ];

  return (
    <section id="premium" className={`${styles.premium} ${styles.rise} scroll-mt-20 p-5 sm:p-8`} style={{ "--i": 2 } as CSSProperties}>
      <div className="grid items-center gap-7 lg:grid-cols-[1.05fr_1fr] lg:gap-10">
        <div className="space-y-6">
          <div className="flex flex-wrap items-center gap-3">
            <span className={styles.seal} aria-hidden="true">
              <Crown className="h-5 w-5" strokeWidth={1.8} />
            </span>
            <span className={`${styles.gold} text-lg font-semibold tracking-tight`}>Պրեմիում</span>
            <span className={`${styles.soonBadge} rounded-full px-2.5 py-0.5 text-[11px] font-medium`}>Շուտով</span>
          </div>

          <div className="space-y-2.5">
            <h2 className="text-balance text-[1.375rem] font-semibold leading-tight tracking-tight sm:text-[1.75rem]">
              Անցեք Պրեմիումի և ստացեք ավելին
            </h2>
            <p className="max-w-lg text-sm leading-relaxed text-[#e4f4ef]/70">
              Ավելի խորը վերլուծություն, ավելի շատ AI հարցումներ և ավելի շատ մոդուլներ՝ մեկ փաթեթում։
            </p>
          </div>

          <ul className={`${styles.benefits} divide-y`}>
            {benefits.map(({ icon: Icon, title, text }, index) => (
              <li
                key={title}
                className={`${styles.benefit} ${styles.rise} flex gap-3.5 px-3.5 py-3`}
                style={{ "--i": index + 3 } as CSSProperties}
              >
                <span className={`${styles.benefitIcon} mt-0.5 inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg`}>
                  <Icon className="h-4 w-4" strokeWidth={1.9} />
                </span>
                <div className="min-w-0">
                  <p className="text-sm font-medium">{title}</p>
                  <p className="mt-0.5 text-xs leading-relaxed text-[#e4f4ef]/60">{text}</p>
                </div>
              </li>
            ))}
          </ul>

          <div className="space-y-2">
            <div className="flex flex-wrap items-baseline justify-between gap-2 text-xs">
              <span className="text-[#e4f4ef]/65">AI հարցումներ այս ամիս</span>
              <span className="font-medium tabular-nums">
                {formatNumber(used)} / {formatNumber(limit)}
              </span>
            </div>
            <div className={`${styles.usageTrack} flex h-1.5 overflow-hidden rounded-full`}>
              <div className={`${styles.usageNow} h-full rounded-full`} style={{ width: `${Math.max(usedShare * 55, used > 0 ? 3 : 0)}%` }} />
              <div className={`${styles.usageMore} ml-auto h-full w-[45%]`} />
            </div>
            <div className="flex justify-between text-[11px] text-[#e4f4ef]/50">
              <span>Ձեր փաթեթը</span>
              <span className={styles.goldSolid}>+ Պրեմիում</span>
            </div>
          </div>

          {canAsk ? (
            <RequestButton
              body={{ kind: "premium" }}
              label="Տեղեկացնել ինձ, երբ հասանելի լինի"
              confirm={{
                title: "Տեղեկացնե՞լ ձեզ Պրեմիումի մասին",
                description:
                  "Պրեմիումը դեռ պատրաստվում է։ Երբ այն հասանելի լինի, Phay-ի թիմը կգրի ձեզ չատում։ Սա ձեզ ոչնչի չի պարտավորեցնում, գումար չի գանձվի։",
                confirmLabel: "Այո, տեղեկացնել",
              }}
              className={`${styles.cta} min-h-11 w-full px-5 py-2.5 leading-snug sm:w-auto`}
              onDark
            />
          ) : null}
        </div>

        <PremiumPreview />
      </div>
    </section>
  );
}

/** A richer analytics screen behind frosted glass, lit by a spotlight. */
function PremiumPreview() {
  const ref = useRef<HTMLDivElement>(null);
  const [following, setFollowing] = useState(false);

  function move(event: PointerEvent<HTMLDivElement>) {
    const box = ref.current?.getBoundingClientRect();
    if (!box || !ref.current) return;
    ref.current.style.setProperty("--x", `${event.clientX - box.left}px`);
    ref.current.style.setProperty("--y", `${event.clientY - box.top}px`);
  }

  return (
    <div
      ref={ref}
      className={`${styles.preview} ${following ? "" : styles.wander} ${styles.rise} h-72 sm:h-80`}
      style={{ "--i": 4 } as CSSProperties}
      onPointerEnter={() => setFollowing(true)}
      onPointerLeave={() => {
        ref.current?.style.removeProperty("--x");
        ref.current?.style.removeProperty("--y");
        setFollowing(false);
      }}
      onPointerMove={move}
      aria-label="Պրեմիումի նախադիտում"
      role="img"
    >
      <div className={styles.previewLayer}>
        <PreviewScreen />
      </div>
      <div className={`${styles.previewLayer} ${styles.previewBlur}`}>
        <PreviewScreen />
      </div>
      <div className={`${styles.previewLayer} ${styles.previewGlow}`} />

      <div className="pointer-events-none absolute inset-x-0 bottom-4 flex justify-center">
        <span className={`${styles.lock} inline-flex items-center gap-2 rounded-full px-3.5 py-1.5 text-xs font-medium`}>
          <Lock className="h-3.5 w-3.5" strokeWidth={2} />
          Հասանելի կլինի Պրեմիումում
        </span>
      </div>
    </div>
  );
}

const BARS = [38, 52, 46, 64, 58, 72, 80];
const FORECAST = [86, 94];

/** What a premium analytics screen could hold; figures are illustrative. */
function PreviewScreen() {
  return (
    <div className="flex h-full flex-col gap-3 p-4" aria-hidden="true">
      <div className="flex items-center justify-between">
        <div>
          <p className="text-[11px] text-ink-muted">Եկամտի կանխատեսում</p>
          <p className="text-sm font-semibold text-ink">Հաջորդ 2 ամիսը</p>
        </div>
        <span className="rounded-full bg-success-light px-2 py-0.5 text-[11px] font-semibold text-success">+18%</span>
      </div>

      <div className="flex flex-1 items-end gap-1.5 rounded-md border border-border bg-white p-3">
        {BARS.map((height, index) => (
          <div
            key={index}
            className={`${styles.bar} flex-1 rounded-t-sm bg-primary`}
            style={{ height: `${height}%`, opacity: 0.55 + index * 0.06, "--i": index } as CSSProperties}
          />
        ))}
        {FORECAST.map((height, index) => (
          <div
            key={`f${index}`}
            className={`${styles.bar} flex-1 rounded-t-sm border-2 border-dashed border-[#f2b544] bg-[#f2b544]/30`}
            style={{ height: `${height}%`, "--i": BARS.length + index } as CSSProperties}
          />
        ))}
      </div>

      <div className="grid grid-cols-3 gap-2">
        {[
          ["Միջին չեկ", "12 400"],
          ["Կրկնվող հաճախորդ", "41%"],
          ["Լավագույն օր", "Շբթ"],
        ].map(([label, value]) => (
          <div key={label} className="rounded-md border border-border bg-white px-2 py-1.5">
            <p className="truncate text-[10px] text-ink-muted">{label}</p>
            <p className="text-sm font-semibold text-ink">{value}</p>
          </div>
        ))}
      </div>

      <div className="flex items-start gap-2 rounded-md bg-primary-light px-3 py-2">
        <Bot className="mt-0.5 h-4 w-4 shrink-0 text-primary" strokeWidth={1.9} />
        <p className="text-[11px] leading-snug text-ink">
          Շաբաթ օրերին պատվերներն ավելի շատ են. ավելացրեք մեկ աշխատակից, և եկամուտը կաճի։
        </p>
      </div>
    </div>
  );
}
