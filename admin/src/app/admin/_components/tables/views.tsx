// Server views of the business's own tables, shared by the orders and
// sales sections (each has thin pages under orders/tables and sales/tables).
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
  getFeatureFlagsAdmin,
  type DataTableSection,
} from "@/lib/adminApi";
import { buttonClasses, PageHeader } from "../ui";
import { RowsGrid } from "./RowsGrid";
import { SectionTabs } from "./SectionTabs";
import { ImportWizard } from "./ImportWizard";
import { NewTableForm } from "./NewTableForm";
import { TableDesigner } from "./TableDesigner";
import { SECTIONS, tablePath } from "./shared";

/** 404 while the section's module (orders, sales) is off for this business. */
export async function requireSectionModule(section: DataTableSection): Promise<void> {
  const flags = await getFeatureFlagsAdmin();
  if (!flags.find((f) => f.key === SECTIONS[section].feature)?.active) notFound();
}

function first(value: string | string[] | undefined): string | undefined {
  return Array.isArray(value) ? value[0] : value;
}

async function loadTable(section: DataTableSection, rawId: string) {
  const id = Number(rawId);
  const table = Number.isInteger(id) ? await getDataTable(id) : null;
  if (!table || table.section !== section) notFound();
  return table;
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
  const me = await requirePermission("orders");
  await requireSectionModule(section);

  const search = first(searchParams.search);
  const page = Number(first(searchParams.page)) || undefined;
  const table = await loadTable(section, id);
  const [tables, rows] = await Promise.all([getDataTables(section), getDataTableRows(table.id, { search, page })]);
  const canDesign = can(me, "catalog");

  return (
    <div className="space-y-6">
      <PageHeader
        title={SECTIONS[section].label}
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
      <SectionTabs section={section} tables={tables} active={table.id} canDesign={canDesign} />
      {table.description ? <p className="text-sm text-ink-muted">{table.description}</p> : null}
      <RowsGrid key={`${table.id}-${search ?? ""}-${page ?? 1}`} table={table} rows={rows.items} meta={rows.meta} />
    </div>
  );
}

export async function NewTableView({ section }: { section: DataTableSection }) {
  await requirePermission("catalog");
  await requireSectionModule(section);
  const tables = await getDataTables(section);

  return (
    <div className="space-y-6">
      <PageHeader
        title={SECTIONS[section].label}
        description="Ստեղծեք ձեր աղյուսակը՝ ձեր բիզնեսի դաշտերով։ Հետո տողերը կարելի է ավելացնել ձեռքով կամ ներմուծել ֆայլից։"
      />
      <SectionTabs section={section} tables={tables} active="new" canDesign />
      <NewTableForm section={section} />
    </div>
  );
}

export async function TableSettingsView({ section, id }: { section: DataTableSection; id: string }) {
  await requirePermission("catalog");
  await requireSectionModule(section);
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
  await requirePermission("orders");
  await requireSectionModule(section);
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
