import Link from "next/link";
import { requirePermission } from "@/lib/adminAuth";
import { can } from "@/lib/roles";
import { getDataTables } from "@/lib/adminApi";
import { buttonClasses, Card, EmptyCard, PageHeader } from "../_components/ui";
import { SectionTabs } from "../_components/tables/SectionTabs";
import { requireSectionModule } from "../_components/tables/views";
import { tablePath } from "../_components/tables/shared";

export const dynamic = "force-dynamic";

/** The sales module: the tables a business that sells goods keeps its sales in. */
export default async function AdminSalesPage() {
  const me = await requirePermission("orders");
  await requireSectionModule("sales");

  const tables = await getDataTables("sales");
  const canDesign = can(me, "catalog");

  return (
    <div className="space-y-6">
      <PageHeader
        title="Վաճառքներ"
        description="Ձեր վաճառքների աղյուսակները՝ ձեր դաշտերով։ Լրացրեք ձեռքով կամ ներմուծեք ֆայլից, իսկ AI օգնականը կվերլուծի դրանք։"
        actions={
          canDesign ? (
            <Link href={tablePath("sales")} className={buttonClasses("primary")}>
              + Նոր աղյուսակ
            </Link>
          ) : null
        }
      />
      {tables.length > 0 ? <SectionTabs section="sales" tables={tables} active="home" canDesign={canDesign} /> : null}

      {tables.length === 0 ? (
        <EmptyCard>
          {canDesign
            ? "Աղյուսակներ դեռ չկան։ Ստեղծեք առաջինը՝ ձեր վաճառքների դաշտերով (ապրանք, քանակ, գին, ամսաթիվ...)։"
            : "Աղյուսակներ դեռ չկան։ Դրանք ստեղծում է բիզնեսի սեփականատերը կամ ադմինիստրատորը։"}
        </EmptyCard>
      ) : (
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
          {tables.map((table) => (
            <Link key={table.id} href={tablePath("sales", table.id)}>
              <Card className="h-full transition-colors hover:border-primary">
                <p className="font-medium text-ink">{table.name}</p>
                {table.description ? <p className="mt-1 text-sm text-ink-muted">{table.description}</p> : null}
                <p className="mt-3 text-xs text-ink-faint">
                  {table.columns.length} սյունակ · {table.rowsCount ?? 0} տող
                </p>
              </Card>
            </Link>
          ))}
        </div>
      )}
    </div>
  );
}
