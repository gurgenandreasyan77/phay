// Server views of the business's own tables, shared by the orders, sales
// and expenses sections (each has thin pages under <section>/tables).
import type { ReactNode } from "react";
import Link from "next/link";
import { notFound } from "next/navigation";
import { requirePermission } from "@/lib/adminAuth";
import { can } from "@/lib/roles";
import {
  getDataImport,
  getDataImports,
  getDataTable,
  getDataTableRows,
  getDataTables,
  getEmployeeStats,
  getLeads,
  type DataTableSection,
  type PeriodKind,
} from "@/lib/adminApi";
import { buttonClasses, Card, EmptyCard, PageHeader } from "../ui";
import { RowsGrid } from "./RowsGrid";
import { TablesList } from "./TablesList";
import { ImportWizard } from "./ImportWizard";
import { NewTableForm } from "./NewTableForm";
import { TableDesigner } from "./TableDesigner";
import { SECTIONS, tablePath, tablesPath } from "./shared";
import { TABLE_PERIOD_LABELS } from "./periods";
import { periodFromParams } from "./PeriodNav";
import { PerformerStats } from "./PerformerStats";

function first(value: string | string[] | undefined): string | undefined {
  return Array.isArray(value) ? value[0] : value;
}

async function loadTable(section: DataTableSection, rawId: string) {
  const id = Number(rawId);
  const table = Number.isInteger(id) ? await getDataTable(id) : null;
  if (!table || table.section !== section) notFound();
  return table;
}

/** How many orders came from the site, and how many are new; null when none ever did. */
async function siteOrders(): Promise<{ total: number; fresh: number } | null> {
  const [all, fresh] = await Promise.all([getLeads({}), getLeads({ status: "new" })]);
  // The list may come unpaginated: then its length is the count.
  const count = (list: Awaited<ReturnType<typeof getLeads>>) => Math.max(list.meta.total, list.items.length);
  return count(all) > 0 ? { total: count(all), fresh: count(fresh) } : null;
}

/** The site's orders, listed with the tables: one more table the site fills in. */
function SiteOrdersCard({ total, fresh }: { total: number; fresh: number }) {
  return (
    <Link href={`${SECTIONS.orders.base}/site`} className="block">
      <Card className="flex flex-wrap items-center justify-between gap-3 transition-colors hover:border-primary">
        <div>
          <p className="font-medium text-ink">Կայքից եկած պատվերներ</p>
          <p className="mt-0.5 text-sm text-ink-muted">Այս աղյուսակը լրացնում է կայքի ձևը։ Նշեք կարգավիճակը և վերջնական գինը։</p>
        </div>
        <p className="text-sm text-ink-muted">
          {total} պատվեր{fresh > 0 ? <span className="ml-2 font-medium text-primary">{fresh} նոր</span> : null}
        </p>
      </Card>
    </Link>
  );
}

/**
 * All tables of a section: the orders and the sales pages. For the owner,
 * also who did the section's orders in a day, week, month, quarter or year.
 */
export async function TablesListView({
  section,
  searchParams = {},
  intro,
  children,
}: {
  section: DataTableSection;
  searchParams?: Record<string, string | string[] | undefined>;
  /** Shown under the header, above the tables. */
  intro?: ReactNode;
  /** More of the section, under the tables. */
  children?: ReactNode;
}) {
  const me = await requirePermission(SECTIONS[section].permission);
  const current = periodFromParams(searchParams);
  const [tables, site, performers] = await Promise.all([
    getDataTables(section),
    section === "orders" ? siteOrders() : null,
    can(me, "insights") && section !== "expenses"
      ? getEmployeeStats({ section, ...(current ? { period: current.kind, date: current.date } : {}) })
      : null,
  ]);
  const canDesign = can(me, SECTIONS[section].design);

  return (
    <div className="space-y-6">
      <PageHeader
        title={SECTIONS[section].label}
        description={
          section === "expenses"
            ? "Ձեր ծախսերի աղյուսակները՝ հարկեր, վարձ, աշխատավարձ, գնումներ… Լրացրեք ձեռքով կամ ներմուծեք ֆայլից։"
            : section === "sales"
            ? "Ձեր վաճառքների աղյուսակները՝ ձեր դաշտերով։ Լրացրեք ձեռքով կամ ներմուծեք ֆայլից, իսկ վերլուծությունը և AI օգնականը կհաշվեն բոլորը միասին։"
            : "Ձեր պատվերների աղյուսակները՝ ձեռքով լրացված կամ ֆայլից ներմուծված։ Վերլուծությունը հաշվում է բոլորը միասին՝ կայքի պատվերների հետ։"
        }
        actions={
          canDesign ? (
            <Link href={tablePath(section)} className={buttonClasses("primary")}>
              + Ներմուծել ֆայլ կամ նոր աղյուսակ
            </Link>
          ) : null
        }
      />
      {intro}
      {site ? <SiteOrdersCard {...site} /> : null}
      {tables.length === 0 ? (
        <EmptyCard>
          {canDesign
            ? section === "expenses"
              ? "Ծախսերի աղյուսակներ դեռ չկան։ Ստեղծեք առաջինը (ամսաթիվ, ծախսի տեսակ, ինչի համար, գումար) կամ Excel ֆայլից։"
              : section === "sales"
              ? "Աղյուսակներ դեռ չկան։ Ստեղծեք առաջինը՝ ձեր վաճառքների դաշտերով (ապրանք, քանակ, գին, ամսաթիվ...) կամ Excel ֆայլից։"
              : "Աղյուսակներ դեռ չկան։ Ստեղծեք առաջինը ձեռքով կամ Excel ֆայլից։"
            : "Աղյուսակներ դեռ չկան։ Դրանք ստեղծում է բիզնեսի սեփականատերը կամ ադմինիստրատորը։"}
        </EmptyCard>
      ) : (
        <TablesList section={section} tables={tables} canDesign={canDesign} />
      )}
      {performers?.hasPerformerColumn ? <PerformerStats stats={performers} base={tablesPath(section)} current={current} /> : null}
      {children}
    </div>
  );
}

function BackToTables({ section }: { section: DataTableSection }) {
  return (
    <Link href={tablesPath(section)} className={buttonClasses("ghost", "sm")}>
      ← Բոլոր աղյուսակները
    </Link>
  );
}

export async function TableView({
  section,
  id,
  searchParams,
}: {
  section: DataTableSection;
  id: string;
  searchParams: Record<string, string | string[] | undefined>;
}) {
  const me = await requirePermission(SECTIONS[section].permission);

  const search = first(searchParams.search);
  const page = Number(first(searchParams.page)) || undefined;
  const period = first(searchParams.period) as PeriodKind | undefined;
  const date = first(searchParams.date);
  const table = await loadTable(section, id);
  const rows = await getDataTableRows(table.id, { search, page, period, date });
  const canDesign = can(me, SECTIONS[section].design);

  return (
    <div className="space-y-6">
      <BackToTables section={section} />
      <PageHeader
        title={table.name}
        actions={
          <>
            <Link href={tablePath(section, table.id, "/import")} className={buttonClasses("secondary")}>
              Ներմուծել ֆայլից
            </Link>
            {canDesign ? (
              <Link href={tablePath(section, table.id, "/settings")} className={buttonClasses("secondary")}>
                Սյունակներ և կարգավորումներ
              </Link>
            ) : null}
          </>
        }
      />
      {table.description ? <p className="text-sm text-ink-muted">{table.description}</p> : null}
      <p className="text-sm text-ink-muted">
        {table.periodInfo
          ? `${TABLE_PERIOD_LABELS[table.periodInfo.kind]} աղյուսակ՝ ${table.periodInfo.label}${table.periodInfo.inferred ? " (ըստ անվան կամ տողերի)" : ""}`
          : "Ցանկացած ամսաթվի աղյուսակ"}
      </p>
      <RowsGrid key={`${table.id}-${search ?? ""}-${page ?? 1}-${period ?? ""}-${date ?? ""}`} table={table} rows={rows.items} meta={rows.meta} />
    </div>
  );
}

export async function NewTableView({ section }: { section: DataTableSection }) {
  await requirePermission(SECTIONS[section].design);

  return (
    <div className="space-y-6">
      <BackToTables section={section} />
      <PageHeader
        title="Նոր աղյուսակ"
        description="Ընտրեք Excel կամ CSV ֆայլ, կամ կազմեք աղյուսակը ձեռքով՝ ձեր բիզնեսի դաշտերով։"
      />
      <NewTableForm section={section} />
    </div>
  );
}

export async function TableSettingsView({ section, id }: { section: DataTableSection; id: string }) {
  await requirePermission(SECTIONS[section].design);
  const table = await loadTable(section, id);

  return (
    <div className="space-y-6">
      <PageHeader
        title={`«${table.name}» աղյուսակի կարգավորումներ`}
        actions={
          <Link href={tablePath(section, table.id)} className={buttonClasses("ghost")}>
            ← Վերադառնալ աղյուսակին
          </Link>
        }
      />
      <TableDesigner key={table.updatedAt} section={section} table={table} />
    </div>
  );
}

export async function ImportView({
  section,
  id,
  searchParams,
}: {
  section: DataTableSection;
  id: string;
  searchParams: Record<string, string | string[] | undefined>;
}) {
  await requirePermission(SECTIONS[section].permission);
  const table = await loadTable(section, id);

  // ?import=: a file already uploaded (a table just made from it).
  const importId = Number(first(searchParams.import));
  const [history, pending] = await Promise.all([
    getDataImports(table.id),
    importId ? getDataImport(importId, table.id) : Promise.resolve(null),
  ]);
  const initialPreview = pending && pending.status === "uploaded" && "sampleRows" in pending ? pending : null;

  return (
    <div className="space-y-6">
      <PageHeader
        title={`Ներմուծում «${table.name}» աղյուսակ`}
        description="Ավելացրեք տողեր CSV, Excel (XLSX) կամ XML ֆայլից։ Սխալ արժեքներով տողերը բաց կթողնվեն, և դուք կտեսնեք՝ ինչու։"
        actions={
          <Link href={tablePath(section, table.id)} className={buttonClasses("ghost")}>
            ← Վերադառնալ աղյուսակին
          </Link>
        }
      />
      <ImportWizard key={initialPreview?.id ?? "upload"} table={table} initialPreview={initialPreview} history={history} />
    </div>
  );
}
