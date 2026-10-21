"use client";

import { useMemo, useState } from "react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { Trash2, TriangleAlert, Upload } from "lucide-react";
import type { AdminDataTableDto, DataTableSection, TablePeriod } from "@/lib/adminApi";
import { Button, buttonClasses, EmptyRow, Input, Select, TableWrap, Td, Th, Thead, Tr } from "../ui";
import { formatDateTime, formatMoment, formatMoney, tablePath } from "./shared";
import { contains, PERIOD_KINDS, TABLE_PERIOD_LABELS } from "./periods";

/** What a table is kept for: its period's kind, or any dates. */
function kindOf(table: AdminDataTableDto): TablePeriod {
  return table.periodInfo?.kind ?? "custom";
}

/**
 * Every table of a section in one list: a business that imports a file a
 * day ends up with dozens, so they're searched, sorted by their latest row
 * and deleted one by one or several at once.
 */
export function TablesList({
  section,
  tables,
  canDesign,
}: {
  section: DataTableSection;
  tables: AdminDataTableDto[];
  canDesign: boolean;
}) {
  const router = useRouter();
  const [search, setSearch] = useState("");
  const [kind, setKind] = useState<TablePeriod | "">("");
  const [selected, setSelected] = useState<Set<number>>(new Set());
  const [pending, setPending] = useState(false);
  const [failed, setFailed] = useState(false);

  const shown = useMemo(() => {
    const query = search.trim().toLowerCase();
    return tables
      .filter((t) => query === "" || t.name.toLowerCase().includes(query))
      .filter((t) => kind === "" || kindOf(t) === kind)
      .sort(
        (a, b) =>
          (b.periodInfo?.start ?? "").localeCompare(a.periodInfo?.start ?? "") ||
          (b.lastAt ?? "").localeCompare(a.lastAt ?? "") ||
          b.id - a.id,
      );
  }, [tables, search, kind]);

  const kinds = [...PERIOD_KINDS, "custom" as const].filter((k) => tables.some((t) => kindOf(t) === k));

  const allShownSelected = shown.length > 0 && shown.every((t) => selected.has(t.id));

  function toggle(id: number) {
    setSelected((current) => {
      const next = new Set(current);
      if (next.has(id)) next.delete(id);
      else next.add(id);
      return next;
    });
  }

  async function remove(ids: number[]) {
    const names = tables.filter((t) => ids.includes(t.id));
    const rows = names.reduce((sum, t) => sum + (t.rowsCount ?? 0), 0);
    const what = names.length === 1 ? `«${names[0].name}» աղյուսակը` : `${names.length} աղյուսակ`;
    if (!confirm(`Ջնջե՞լ ${what} և ${rows} տողերը՝ ներմուծումների պատմության հետ միասին։ Սա հնարավոր չէ հետ բերել։`)) return;

    setPending(true);
    setFailed(false);
    const response = await fetch("/api/admin/data-tables/bulk-delete", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ ids }),
    }).catch(() => null);
    setPending(false);

    if (!response?.ok) {
      setFailed(true);
      return;
    }
    setSelected(new Set());
    router.refresh();
  }

  return (
    <div className="space-y-4">
      <div className="flex flex-wrap items-center gap-3">
        <Input value={search} onChange={(e) => setSearch(e.target.value)} placeholder="Փնտրել անունով" className="w-64" />
        {kinds.length > 1 ? (
          <Select className="w-56" value={kind} onChange={(e) => setKind(e.target.value as TablePeriod | "")} aria-label="Ժամանակահատված">
            <option value="">Բոլոր ժամանակահատվածները</option>
            {kinds.map((k) => (
              <option key={k} value={k}>
                {TABLE_PERIOD_LABELS[k]}
              </option>
            ))}
          </Select>
        ) : null}
        <span className="text-sm text-ink-muted">{tables.length} աղյուսակ</span>
        {canDesign && selected.size > 0 ? (
          <Button variant="secondary" className="ml-auto" disabled={pending} onClick={() => remove([...selected])}>
            <Trash2 className="h-4 w-4" />
            Ջնջել ընտրվածները ({selected.size})
          </Button>
        ) : null}
        {failed ? <span className="text-sm text-danger">Չհաջողվեց ջնջել։</span> : null}
      </div>

      <TableWrap>
        <Thead>
          {canDesign ? (
            <Th className="w-10">
              <input
                type="checkbox"
                aria-label="Ընտրել բոլորը"
                checked={allShownSelected}
                onChange={() => setSelected(allShownSelected ? new Set() : new Set(shown.map((t) => t.id)))}
              />
            </Th>
          ) : null}
          <Th>Աղյուսակ</Th>
          <Th>Ժամանակահատված</Th>
          <Th className="text-right">Տողեր</Th>
          <Th>Ամսաթվերը</Th>
          <Th className="text-right">Գումար</Th>
          <Th>Վերջին ներմուծում</Th>
          <Th className="w-24" />
        </Thead>
        <tbody>
          {shown.map((table) => (
            <Tr key={table.id}>
              {canDesign ? (
                <Td>
                  <input
                    type="checkbox"
                    aria-label={`Ընտրել «${table.name}»`}
                    checked={selected.has(table.id)}
                    onChange={() => toggle(table.id)}
                  />
                </Td>
              ) : null}
              <Td>
                <Link href={tablePath(section, table.id)} className="font-medium text-ink hover:text-primary">
                  {table.name}
                </Link>
                <span className="block text-xs text-ink-faint">
                  {table.columns.length} սյունակ
                  {table.source === "google" ? " · Google օրացույցից" : ""}
                </span>
                {table.archivedAt ? (
                  <span className="mt-1 block text-xs text-warning">Հին տարբերակ · չի հաշվվում վերլուծության մեջ</span>
                ) : null}
              </Td>
              <Td className="whitespace-nowrap">
                {table.periodInfo ? (
                  <>
                    <span className="text-ink">{table.periodInfo.label}</span>
                    <span className="block text-xs text-ink-faint">
                      {TABLE_PERIOD_LABELS[table.periodInfo.kind]}
                      {table.periodInfo.inferred ? " · ըստ անվան" : ""}
                    </span>
                    {(table.firstAt && !contains(table.periodInfo, table.firstAt)) ||
                    (table.lastAt && !contains(table.periodInfo, table.lastAt)) ? (
                      <span className="mt-0.5 flex items-center gap-1 text-xs text-warning">
                        <TriangleAlert className="h-3 w-3" />
                        Կան տողեր այլ ժամանակահատվածից
                      </span>
                    ) : null}
                  </>
                ) : (
                  <span className="text-ink-muted">{TABLE_PERIOD_LABELS.custom}</span>
                )}
              </Td>
              <Td className="text-right">{table.rowsCount ?? 0}</Td>
              <Td className="whitespace-nowrap text-ink-muted">
                {table.firstAt && table.lastAt
                  ? table.firstAt.slice(0, 10) === table.lastAt.slice(0, 10)
                    ? formatMoment(table.firstAt.slice(0, 10))
                    : `${formatMoment(table.firstAt.slice(0, 10))} – ${formatMoment(table.lastAt.slice(0, 10))}`
                  : "—"}
                {table.rowsWithoutDate ? (
                  <span className="mt-0.5 flex items-center gap-1 text-xs text-warning">
                    <TriangleAlert className="h-3 w-3" />
                    {table.rowsWithoutDate} տող առանց ամսաթվի
                  </span>
                ) : null}
                {!table.dateColumn ? (
                  <span className="mt-0.5 flex items-center gap-1 text-xs text-warning">
                    <TriangleAlert className="h-3 w-3" />
                    Ամսաթվի սյունակ ընտրված չէ
                  </span>
                ) : null}
              </Td>
              <Td className="whitespace-nowrap text-right">
                {table.amountColumn ? formatMoney(table.amountTotal ?? 0) : "—"}
              </Td>
              <Td className="whitespace-nowrap text-ink-muted">
                {formatDateTime(table.lastImportAt)}
              </Td>
              <Td className="whitespace-nowrap text-right">
                <Link
                  href={tablePath(section, table.id, "/import")}
                  className={buttonClasses("ghost", "sm")}
                  aria-label="Ներմուծել ֆայլից"
                  title="Ներմուծել ֆայլից"
                >
                  <Upload className="h-4 w-4" />
                </Link>
                {canDesign ? (
                  <Button
                    variant="ghost"
                    size="sm"
                    aria-label="Ջնջել"
                    title="Ջնջել"
                    disabled={pending}
                    onClick={() => remove([table.id])}
                  >
                    <Trash2 className="h-4 w-4" />
                  </Button>
                ) : null}
              </Td>
            </Tr>
          ))}
          {shown.length === 0 ? (
            <EmptyRow colSpan={canDesign ? 8 : 7}>
              {tables.length === 0 ? "Աղյուսակներ դեռ չկան։" : "Ոչինչ չի գտնվել։"}
            </EmptyRow>
          ) : null}
        </tbody>
      </TableWrap>
    </div>
  );
}
