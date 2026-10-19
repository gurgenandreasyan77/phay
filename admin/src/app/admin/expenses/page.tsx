import Link from "next/link";
import { requirePermission } from "@/lib/adminAuth";
import { can } from "@/lib/roles";
import { getExpenseCategories, getFinance, getRecurringExpenses } from "@/lib/adminApi";
import { TablesListView } from "../_components/tables/views";
import { SectionCard } from "../_components/ui";
import { FinanceCategories, FinanceNotes, FinanceTiles } from "../_components/finance/Finance";
import { CategoriesManager } from "./CategoriesManager";
import { RecurringManager } from "./RecurringManager";

export const dynamic = "force-dynamic";

/**
 * What the business spends: this month's net profit and where the money
 * went, the expenses tables (by hand or from files), the expenses that
 * repeat and the kinds of expenses.
 */
export default async function AdminExpensesPage(props: PageProps<"/admin/expenses">) {
  const me = await requirePermission("expenses");
  const [categories, recurring, finance] = await Promise.all([
    getExpenseCategories(),
    getRecurringExpenses(),
    can(me, "insights") ? getFinance({ by: "month" }) : null,
  ]);

  const month = finance?.current;
  const intro =
    finance && month && finance.has.expenses ? (
      <SectionCard
        title={`${month.label}՝ զուտ շահույթ`}
        description="Եկամուտ − ապրանքի ինքնարժեք − ծախսեր։ Ամիսը դեռ չի ավարտվել։"
        actions={
          <Link href="/admin/statistics?by=month#finance" className="text-sm font-medium text-primary hover:underline">
            Ըստ օրերի, ամիսների, եռամսյակների →
          </Link>
        }
      >
        <div className="grid gap-6 lg:grid-cols-[2fr_1fr]">
          <FinanceTiles period={month} previous={month.previous} withCost={finance.has.cost} />
          <FinanceCategories categories={month.categories} />
        </div>
        <FinanceNotes notes={finance.notes} />
      </SectionCard>
    ) : null;

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
