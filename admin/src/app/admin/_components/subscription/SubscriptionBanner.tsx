import Link from "next/link";
import { AlertTriangle, Lock } from "lucide-react";
import type { SubscriptionSummaryDto } from "@/lib/adminApi";
import { cn } from "@/ui";
import { daysLeftLabel, formatDay } from "./shared";

/**
 * Above every page of a business whose subscription ends soon (the warning
 * window) or has ended. Platform staff don't see it: they manage
 * subscriptions from the platform level.
 */
export function SubscriptionBanner({ subscription }: { subscription: SubscriptionSummaryDto }) {
  const locked = subscription.locked;

  return (
    <div
      className={cn(
        "flex flex-wrap items-center gap-x-3 gap-y-2 border-b px-4 py-3 text-sm sm:px-6 lg:px-8",
        locked ? "border-danger/30 bg-danger-light text-danger" : "border-warning/30 bg-warning-light text-warning",
      )}
    >
      {locked ? <Lock className="h-4 w-4 shrink-0" /> : <AlertTriangle className="h-4 w-4 shrink-0" />}
      <p className="flex-1">
        {locked
          ? "Բաժանորդագրությունն ավարտվել է, ադմինը ժամանակավորապես փակ է։ Ձեր բոլոր տվյալները պահպանված են. վճարելուց հետո մուտքը կվերականգնվի։"
          : subscription.status === "canceling"
            ? `Բաժանորդագրությունը կանջատվի ${formatDay(subscription.endsAt)}-ին (${daysLeftLabel(subscription.daysLeft)} հետո)։ Վճարեք, որպեսզի ադմինը շարունակի աշխատել։`
            : `Բաժանորդագրությունն ավարտվում է ${formatDay(subscription.endsAt)}-ին (${daysLeftLabel(subscription.daysLeft)} հետո)։ Երկարացրեք, որպեսզի ադմինը շարունակի աշխատել։`}
      </p>
      <Link href="/admin/subscription" className="font-medium underline underline-offset-2">
        {locked ? "Վերականգնել" : "Երկարացնել"}
      </Link>
    </div>
  );
}
