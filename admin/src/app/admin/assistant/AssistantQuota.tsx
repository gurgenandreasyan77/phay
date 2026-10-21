"use client";

import Link from "next/link";
import { CalendarClock, Crown, Gift, Sparkles } from "lucide-react";
import type { AssistantPlanOfferDto, AssistantUsageDto } from "@/lib/adminApi";
import { Card, cn } from "../_components/ui";
import { amd, formatDay } from "../_components/subscription/shared";
import { formatNumber } from "../_components/tables/shared";
import { RequestButton } from "../subscription/RequestButton";

/**
 * How many questions are left this subscription month, and when new ones
 * come (the day of the month the business paid, not the 1st).
 */
export function AssistantQuotaBar({ usage }: { usage: AssistantUsageDto }) {
  if (usage.unlimited || usage.limit === null || usage.remaining === null) {
    return (
      <div className="flex items-center gap-2 text-xs text-ink-faint">
        <Sparkles className="h-3.5 w-3.5 text-primary" />
        <span>Հարցերի քանակը սահմանափակված չէ։</span>
      </div>
    );
  }

  const share = usage.limit > 0 ? Math.min(1, usage.used / usage.limit) : 1;
  const low = usage.remaining <= Math.max(3, Math.round(usage.limit * 0.1));

  return (
    <Card className="space-y-2 py-3">
      <div className="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1">
        <p className="text-sm text-ink">
          Մնացել է{" "}
          <span className={cn("text-base font-semibold tabular-nums", low ? "text-danger" : "text-ink")}>
            {formatNumber(usage.remaining)}
          </span>{" "}
          հարց
          <span className="text-ink-muted"> · օգտագործված՝ {formatNumber(usage.used)} / {formatNumber(usage.limit)}</span>
        </p>
        <p className="flex items-center gap-1.5 text-xs text-ink-muted">
          <CalendarClock className="h-3.5 w-3.5" />
          Նոր հարցերը՝ {formatDay(usage.resetsAt)}
        </p>
      </div>
      <div className="h-1.5 overflow-hidden rounded-full bg-surface-secondary" role="progressbar" aria-valuemin={0} aria-valuemax={usage.limit} aria-valuenow={usage.used}>
        <div className={cn("h-full rounded-full", low ? "bg-danger" : "bg-primary")} style={{ width: `${share * 100}%` }} />
      </div>
      {usage.bonus > 0 ? (
        <p className="flex items-center gap-1.5 text-xs text-ink-muted">
          <Gift className="h-3.5 w-3.5 text-primary" />
          Այդ թվում {formatNumber(usage.bonus)} նվեր հարց. դրանք չեն այրվում ամսվա վերջում։
        </p>
      ) : null}
    </Card>
  );
}

/**
 * The questions ran out: a plan with more of them, more questions from the
 * team, or simply the day new ones come.
 */
export function AssistantLimitReached({
  usage,
  plans,
  canAsk,
}: {
  usage: AssistantUsageDto;
  plans: AssistantPlanOfferDto[];
  canAsk: boolean;
}) {
  const noAssistant = usage.planLimit === 0 && usage.bonus === 0 && usage.used === 0;

  return (
    <Card className="space-y-4 border-primary/30 bg-primary-light/40">
      <div className="space-y-1">
        <p className="font-semibold text-ink">
          {noAssistant ? "Ձեր փաթեթում AI օգնական չկա" : "Այս ամսվա հարցերը սպառվել են"}
        </p>
        <p className="text-sm text-ink-muted">
          {noAssistant
            ? "Ընտրեք փաթեթ, որի մեջ ներառված են AI օգնականի հարցեր։"
            : `Նոր հարցերը կհասանելի դառնան ${formatDay(usage.resetsAt)}-ին՝ ձեր բաժանորդագրության ամսվա սկզբին։ Եթե հիմա պետք է, ընտրեք ավելի մեծ փաթեթ։`}
        </p>
      </div>

      {plans.length > 0 ? (
        <div className="grid gap-3 sm:grid-cols-2">
          {plans.map((plan) => (
            <div key={plan.id} className="flex flex-col gap-3 rounded-lg border border-border bg-surface p-4">
              <div className="flex items-start justify-between gap-3">
                <p className="flex items-center gap-2 font-medium text-ink">
                  <Crown className="h-4 w-4 text-primary" />
                  {plan.name}
                </p>
                <p className="text-sm font-semibold text-ink">{amd(plan.price)}/ամիս</p>
              </div>
              <p className="text-sm text-ink-muted">
                {plan.assistantRequests === null
                  ? "AI հարցեր՝ առանց սահմանափակման"
                  : `${formatNumber(plan.assistantRequests)} AI հարց ամսական`}
              </p>
              {canAsk ? (
                <RequestButton
                  body={{ kind: "plan", plan_id: plan.id }}
                  label={`Անցնել «${plan.name}» փաթեթին`}
                  primary
                  confirm={{
                    title: `Անցնե՞լ «${plan.name}» փաթեթին`,
                    description: "Հայտը կուղարկվի Servio-ի թիմին. փաթեթը փոխելուց հետո նոր սահմանաչափը կգործի անմիջապես։",
                    details: [
                      { label: "Ամսական վճար", value: amd(plan.price) },
                      {
                        label: "AI հարցեր",
                        value: plan.assistantRequests === null ? "Առանց սահմանափակման" : `${formatNumber(plan.assistantRequests)} ամսական`,
                      },
                    ],
                    confirmLabel: "Այո, ուղարկել",
                  }}
                />
              ) : null}
            </div>
          ))}
        </div>
      ) : null}

      {canAsk ? (
        <div className="flex flex-wrap items-center gap-3">
          {plans.length === 0 ? (
            <RequestButton
              body={{ kind: "premium" }}
              label="Հետաքրքրում է Պրեմիումը"
              primary
              confirm={{
                title: "Տեղեկացնե՞լ ձեզ Պրեմիումի մասին",
                description: "Հայտը կուղարկվի Servio-ի թիմին. Պրեմիումում AI հարցերը շատ ավելի են։",
                confirmLabel: "Այո, ուղարկել",
              }}
            />
          ) : null}
          <RequestButton
            body={{ kind: "assistant" }}
            label="Խնդրել լրացուցիչ հարցեր"
            confirm={{
              title: "Խնդրե՞լ լրացուցիչ հարցեր",
              description: "Հայտը կուղարկվի Servio-ի թիմին, որը չատում կպատասխանի։",
              confirmLabel: "Այո, ուղարկել",
            }}
          />
        </div>
      ) : (
        <p className="text-sm text-ink-muted">
          Փաթեթը փոխելու համար դիմեք բիզնեսի սեփականատիրոջը կամ բացեք{" "}
          <Link href="/admin/subscription" className="font-medium underline underline-offset-2">
            Բաժանորդագրություն
          </Link>
          ։
        </p>
      )}
    </Card>
  );
}
