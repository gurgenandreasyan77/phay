import Link from "next/link";
import { requirePermission } from "@/lib/adminAuth";
import { getExpenseCategories, getExpenseStatistics, getRecurringExpenses } from "@/lib/adminApi";
import { TablesListView } from "../_components/tables/views";
import { Card, cn, SectionCard } from "@/ui";
import { money } from "../_components/analytics/format";
import { FinanceCategories } from "../_components/finance/Finance";
import { CategoriesManager } from "./CategoriesManager";
import { ExpensesTabs } from "./ExpensesTabs";
import { RecurringManager } from "./RecurringManager";

export const dynamic = "force-dynamic";

/**
 * What the business spends: this month's expenses on their own (no sales
 * mixed in) and where the money went, the expenses tables (by hand or from
 * files), the expenses that repeat and the kinds of expenses. The full
 * statistics for any day, week, month, quarter or year are on their own tab.
 */
export default async function AdminExpensesPage(props: PageProps<"/admin/expenses">) {
  await requirePermission("expenses");
  const [categories, recurring, month] = await Promise.all([
    getExpenseCategories(),
    getRecurringExpenses(),
    getExpenseStatistics({ by: "month" }),
  ]);

  const delta = month ? month.totals.total - month.totals.previous : 0;
  const intro = (
    <div className="space-y-4">
      <ExpensesTabs active="list" />
      {month && month.totals.count > 0 ? (
        <SectionCard
          title={`${month.period.label}՝ ծախսեր`}
          description="Միայն ծախսերը՝ առանց վաճառքների։ Ամիսը դեռ չի ավարտվել։"
          actions={
            <Link href="/admin/expenses/statistics" className="text-sm font-medium text-primary hover:underline">
              Ըստ օրերի, շաբաթների, ամիսների, տարիների →
            </Link>
          }
        >
          <div className="grid gap-6 lg:grid-cols-[1fr_2fr]">
            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-1">
              <Card className="space-y-1">
                <p className="text-xs text-ink-muted">Ծախսվել է</p>
                <p className="text-2xl font-semibold tabular-nums text-ink">{money(month.totals.total)}</p>
                {delta !== 0 ? (
                  <p className={cn("text-xs", delta > 0 ? "text-danger" : "text-success")}>
                    {delta > 0 ? "+" : "−"}
                    {money(Math.abs(delta))} · {month.previous.label}
                  </p>
                ) : null}
              </Card>
              <Card className="space-y-1">
                <p className="text-xs text-ink-muted">Կրկնվող / միանվագ</p>
                <p className="text-lg font-semibold tabular-nums text-ink">
                  {money(month.totals.recurring)} <span className="text-ink-faint">/</span> {money(month.totals.oneOff)}
                </p>
              </Card>
            </div>
            <FinanceCategories categories={month.categories} />
          </div>
        </SectionCard>
      ) : null}
    </div>
  );

  return (
    <TablesListView section="expenses" searchParams={await props.searchParams} intro={intro}>
      <SectionCard
        title="Կրկնվող ծախսեր"
        description="Վարձ, աշխատավարձ, հարկեր… Գրեք մեկ անգամ՝ դրանք կհաշվվեն ամեն վճարման օրը, ձեռքով նորից գրելու կարիք չկա։"
      >
        <RecurringManager expenses={recurring} categories={categories} />
      </SectionCard>
      <SectionCard
        title="Ծախսերի տեսակներ"
        description="Ընտրեք, թե որ տեսակներն են հանվում զուտ շահույթից։ Ֆայլից եկած «Налоги», «Аренда», «Tax» և նման անունները ինքնաշխատ կճանաչվեն։"
      >
        <CategoriesManager categories={categories} />
      </SectionCard>
    </TablesListView>
  );
}
