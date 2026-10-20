import { Check } from "lucide-react";
import type { CSSProperties } from "react";
import type { SubscriptionModulePriceDto } from "@/lib/adminApi";
import { amd } from "../_components/subscription/shared";
import { ModuleArt, tintStyle } from "./ModuleArt";
import { RequestButton } from "./RequestButton";
import styles from "./subscription.module.css";

/** One module: its picture in its colour, what it gives, its price and how to get it. */
export function ModuleCard({
  module,
  canAsk,
  index,
}: {
  module: SubscriptionModulePriceDto;
  canAsk: boolean;
  index: number;
}) {
  return (
    <article
      className={`${styles.module} ${styles.rise} flex flex-col overflow-hidden rounded-xl border border-border bg-surface`}
      style={{ ...tintStyle(module.key), "--i": index } as CSSProperties}
    >
      <ModuleArt moduleKey={module.key} />
      <div className="flex flex-1 flex-col gap-3 p-5">
        <div className="flex items-start justify-between gap-3">
          <h3 className="font-semibold text-ink">{module.label}</h3>
          {module.active ? (
            <span className={`${styles.tintPill} inline-flex shrink-0 items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium`}>
              <Check className="h-3.5 w-3.5" strokeWidth={2.2} />
              Միացված է
            </span>
          ) : null}
        </div>
        {module.description ? <p className="text-sm leading-relaxed text-ink-muted">{module.description}</p> : null}
        <div className="mt-auto flex flex-wrap items-end justify-between gap-3 pt-1">
          {module.included ? (
            <p className={`${styles.tintText} text-sm font-semibold`}>Ներառված է փաթեթում</p>
          ) : (
            <p className="text-ink">
              <span className={`${styles.tintText} text-xl font-semibold tracking-tight`}>+ {amd(module.price)}</span>
              <span className="text-sm text-ink-muted">/ամիս</span>
            </p>
          )}
          {canAsk && !module.active ? (
            <RequestButton body={{ kind: "module", module: module.key }} label="Միացնել" className={`${styles.tintButton} h-9`} />
          ) : null}
        </div>
      </div>
    </article>
  );
}
