"use client";

import { useEffect, useState } from "react";
import { usePathname, useRouter, useSearchParams } from "next/navigation";
import { ChevronLeft, ChevronRight, Pencil, Trash2 } from "lucide-react";
import type { AdminDataTableDto, AdminDataTableRowDto, AdminEmployeeDto, DataCellValue, PeriodKind } from "@/lib/adminApi";
import { Badge, Button, EmptyRow, Input, Select, TableWrap, Td, Th, Thead, Tr } from "../ui";
import { formatCell, validationErrors } from "./shared";
import { PERIOD_KINDS, PERIOD_UNIT_LABELS, periodOf, shiftPeriod, today } from "./periods";

type Column = AdminDataTableDto["columns"][number];

/** What the inputs hold while a row is edited: always strings. */
export type Draft = Record<string, string>;

/** Now, as a date or date-and-time column stores it ("Y-m-d" / "Y-m-d H:i"). */
export function now(type: Column["type"]): string {
  const d = new Date();
  const pad = (n: number) => String(n).padStart(2, "0");
  const date = `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
  return type === "datetime" ? `${date} ${pad(d.getHours())}:${pad(d.getMinutes())}` : date;
}

/** @param date a new row's date ("Y-m-d"), today when not given */
export function toDraft(table: AdminDataTableDto, row?: AdminDataTableRowDto, date?: string): Draft {
  return Object.fromEntries(
    table.columns.map((column) => {
      // A new row happens now (or on the given day), unless changed.
      if (!row && column.key === table.dateColumn) {
        const today = now(column.type);
        return [column.key, date ? `${date}${today.slice(10)}` : today];
      }
      const value = row?.values[column.key];
      return [column.key, value === null || value === undefined ? "" : String(value)];
    }),
  );
}

/** Strings to what the API takes; it parses and validates each value by type. */
export function fromDraft(table: AdminDataTableDto, draft: Draft): Record<string, DataCellValue> {
  return Object.fromEntries(
    table.columns.map((column) => {
      const value = draft[column.key] ?? "";
      if (value === "") return [column.key, null];
      if (column.type === "boolean") return [column.key, value === "true"];
      return [column.key, value];
    }),
  );
}

export function RowsGrid({
  table,
  rows,
  meta,
}: {
  table: AdminDataTableDto;
  rows: AdminDataTableRowDto[];
  meta: { currentPage: number; lastPage: number; total: number };
}) {
  const router = useRouter();
  const pathname = usePathname();
  const searchParams = useSearchParams();
  const [search, setSearch] = useState(searchParams.get("search") ?? "");
  /** The row being edited: a row id, "new", or none. */
  const [editing, setEditing] = useState<number | "new" | null>(null);

  function go(params: Record<string, string | null>) {
    const next = new URLSearchParams(searchParams.toString());
    for (const [key, value] of Object.entries(params)) {
      if (value) next.set(key, value);
      else next.delete(key);
    }
    router.push(`${pathname}?${next.toString()}`);
  }

  async function remove(row: AdminDataTableRowDto) {
    if (!confirm("Ջնջե՞լ այս տողը։")) return;
    const response = await fetch(`/api/admin/data-tables/${table.id}/rows/${row.id}`, { method: "DELETE" });
    if (response.ok) router.refresh();
  }

  const colSpan = table.columns.length + 1;

  return (
    <div className="space-y-4">
      <div className="flex flex-wrap items-center gap-3">
        <form
          className="flex gap-2"
          onSubmit={(e) => {
            e.preventDefault();
            go({ search: search || null, page: null });
          }}
        >
          <Input value={search} onChange={(e) => setSearch(e.target.value)} placeholder="Փնտրել" className="w-64" />
          <Button type="submit" variant="secondary">
            Փնտրել
          </Button>
        </form>
        {table.dateColumn ? <PeriodFilter table={table} go={go} /> : null}
        <span className="text-sm text-ink-muted">Ընդամենը՝ {meta.total} տող</span>
        <Button className="ml-auto" onClick={() => setEditing("new")} disabled={editing === "new"}>
          + Ավելացնել տող
        </Button>
      </div>

      <TableWrap>
        <Thead>
          {table.columns.map((column) => (
            <Th key={column.key} className="whitespace-nowrap">
              {column.label}
              {column.required || column.key === table.dateColumn ? <span className="text-danger"> *</span> : null}
            </Th>
          ))}
          <Th className="w-24" />
        </Thead>
        <tbody>
          {editing === "new" ? (
            <RowEditor
              table={table}
              onDone={(saved) => {
                setEditing(null);
                if (saved) router.refresh();
              }}
            />
          ) : null}
          {rows.map((row) =>
            editing === row.id ? (
              <RowEditor
                key={row.id}
                table={table}
                row={row}
                onDone={(saved) => {
                  setEditing(null);
                  if (saved) router.refresh();
                }}
              />
            ) : (
              <Tr key={row.id}>
                {table.columns.map((column) => (
                  <Td key={column.key} className={column.type === "money" || column.type === "number" ? "whitespace-nowrap text-right" : ""}>
                    {formatCell(row.values[column.key], column)}
                  </Td>
                ))}
                <Td className="whitespace-nowrap text-right">
                  {row.source === "import" ? (
                    <Badge tone="info" className="mr-2">
                      Ֆայլից
                    </Badge>
                  ) : null}
                  <Button variant="ghost" size="sm" aria-label="Խմբագրել" onClick={() => setEditing(row.id)}>
                    <Pencil className="h-4 w-4" />
                  </Button>
                  <Button variant="ghost" size="sm" aria-label="Ջնջել" onClick={() => remove(row)}>
                    <Trash2 className="h-4 w-4" />
                  </Button>
                </Td>
              </Tr>
            ),
          )}
          {rows.length === 0 && editing !== "new" ? (
            <EmptyRow colSpan={colSpan}>
              {searchParams.get("search") || searchParams.get("period") ? "Ոչինչ չի գտնվել։" : "Տողեր դեռ չկան։ Ավելացրեք առաջինը։"}
            </EmptyRow>
          ) : null}
        </tbody>
      </TableWrap>

      {meta.lastPage > 1 ? (
        <div className="flex items-center gap-3 text-sm text-ink-muted">
          <Button
            variant="secondary"
            size="sm"
            disabled={meta.currentPage <= 1}
            onClick={() => go({ page: String(meta.currentPage - 1) })}
          >
            Նախորդ
          </Button>
          <span>
            {meta.currentPage} / {meta.lastPage}
          </span>
          <Button
            variant="secondary"
            size="sm"
            disabled={meta.currentPage >= meta.lastPage}
            onClick={() => go({ page: String(meta.currentPage + 1) })}
          >
            Հաջորդ
          </Button>
        </div>
      ) : null}
    </div>
  );
}

function RowEditor({
  table,
  row,
  onDone,
}: {
  table: AdminDataTableDto;
  row?: AdminDataTableRowDto;
  onDone: (saved: boolean) => void;
}) {
  const [draft, setDraft] = useState<Draft>(() => toDraft(table, row));
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [pending, setPending] = useState(false);
  const [failed, setFailed] = useState(false);

  async function save() {
    setPending(true);
    setErrors({});
    setFailed(false);
    try {
      const response = await fetch(
        row ? `/api/admin/data-tables/${table.id}/rows/${row.id}` : `/api/admin/data-tables/${table.id}/rows`,
        {
          method: row ? "PATCH" : "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({ values: fromDraft(table, draft) }),
        },
      );
      if (response.status === 422) {
        setErrors(validationErrors(await response.json().catch(() => null)));
        return;
      }
      if (!response.ok) {
        setFailed(true);
        return;
      }
      onDone(true);
    } catch {
      setFailed(true);
    } finally {
      setPending(false);
    }
  }

  return (
    <tr className="border-b border-border bg-primary-light/40 align-top">
      {table.columns.map((column) => (
        <td key={column.key} className="min-w-36 px-2 py-2">
          <CellInput
            column={column}
            value={draft[column.key] ?? ""}
            onChange={(value) => setDraft((d) => ({ ...d, [column.key]: value }))}
          />
          {errors[`values.${column.key}`] ? (
            <p className="mt-1 text-xs text-danger">{errors[`values.${column.key}`]}</p>
          ) : null}
        </td>
      ))}
      <td className="whitespace-nowrap px-2 py-2 text-right">
        <div className="flex flex-col items-end gap-1.5">
          <Button size="sm" onClick={save} disabled={pending}>
            Պահպանել
          </Button>
          <Button size="sm" variant="ghost" onClick={() => onDone(false)} disabled={pending}>
            Չեղարկել
          </Button>
          {failed ? <span className="text-xs text-danger">Չհաջողվեց պահպանել։</span> : null}
        </div>
      </td>
    </tr>
  );
}

/**
 * Rows of one day, week, month, quarter or year (?period=&date=), stepped
 * with the arrows; all rows by default.
 */
function PeriodFilter({ table, go }: { table: AdminDataTableDto; go: (params: Record<string, string | null>) => void }) {
  const searchParams = useSearchParams();
  const kind = searchParams.get("period") as PeriodKind | null;
  const date = searchParams.get("date") ?? "";
  const current = kind && PERIOD_KINDS.includes(kind) && /^\d{4}-\d{2}-\d{2}$/.test(date) ? periodOf(kind, date) : null;

  function choose(next: PeriodKind | "") {
    if (next === "") {
      go({ period: null, date: null, page: null });
      return;
    }
    // The day shown so far, else the table's own period, else its last row, else today.
    const from = current?.start ?? table.periodInfo?.start ?? table.lastAt?.slice(0, 10) ?? today();
    go({ period: next, date: periodOf(next, from).start, page: null });
  }

  return (
    <div className="flex items-center gap-1">
      <Select className="w-36" value={current?.kind ?? ""} onChange={(e) => choose(e.target.value as PeriodKind | "")} aria-label="Ժամանակահատված">
        <option value="">Բոլոր տողերը</option>
        {PERIOD_KINDS.map((k) => (
          <option key={k} value={k}>
            {PERIOD_UNIT_LABELS[k]}
          </option>
        ))}
      </Select>
      {current ? (
        <>
          <Button variant="ghost" size="sm" aria-label="Նախորդ" onClick={() => go({ date: shiftPeriod(current.kind, current.start, -1).start, page: null })}>
            <ChevronLeft className="h-4 w-4" />
          </Button>
          <span className="whitespace-nowrap text-sm font-medium text-ink">{current.label}</span>
          <Button variant="ghost" size="sm" aria-label="Հաջորդ" onClick={() => go({ date: shiftPeriod(current.kind, current.start, 1).start, page: null })}>
            <ChevronRight className="h-4 w-4" />
          </Button>
        </>
      ) : null}
    </div>
  );
}

/** The business's employees, read once per page load: the performer field offers their names. */
let employeesRequest: Promise<AdminEmployeeDto[]> | null = null;

function useEmployees(): AdminEmployeeDto[] {
  const [employees, setEmployees] = useState<AdminEmployeeDto[]>([]);
  useEffect(() => {
    employeesRequest ??= fetch("/api/admin/employees")
      .then((r) => (r.ok ? r.json() : { data: [] }))
      .then((json: { data?: AdminEmployeeDto[] }) => json.data ?? [])
      .catch(() => []);
    let alive = true;
    employeesRequest.then((list) => alive && setEmployees(list));
    return () => {
      alive = false;
    };
  }, []);
  return employees;
}

/** Who did the order: an employee's name, typed or picked from the list. */
function PerformerInput({ column, value, onChange }: { column: Column; value: string; onChange: (value: string) => void }) {
  const employees = useEmployees();
  const listId = `performers-${column.key}`;
  const known = value.trim() === "" || employees.some((e) => e.name.toLowerCase() === value.trim().toLowerCase());

  return (
    <>
      <Input list={listId} value={value} onChange={(e) => onChange(e.target.value)} placeholder="Աշխատակից" />
      <datalist id={listId}>
        {employees
          .filter((e) => e.isActive)
          .map((e) => (
            <option key={e.id} value={e.name}>
              {e.position ?? ""}
            </option>
          ))}
      </datalist>
      {!known && employees.length > 0 ? (
        <p className="mt-1 text-xs text-warning">Աշխատակիցների ցանկում չկա. ավելացրեք «Աշխատակիցներ» բաժնում։</p>
      ) : null}
    </>
  );
}

export function CellInput({ column, value, onChange }: { column: Column; value: string; onChange: (value: string) => void }) {
  if (column.role === "performer" && column.type === "text") {
    return <PerformerInput column={column} value={value} onChange={onChange} />;
  }

  switch (column.type) {
    case "select":
      return (
        <Select value={value} onChange={(e) => onChange(e.target.value)}>
          <option value="">—</option>
          {column.options.map((option) => (
            <option key={option} value={option}>
              {option}
            </option>
          ))}
        </Select>
      );
    case "boolean":
      return (
        <Select value={value} onChange={(e) => onChange(e.target.value)}>
          <option value="">—</option>
          <option value="true">Այո</option>
          <option value="false">Ոչ</option>
        </Select>
      );
    case "date":
      return <Input type="date" value={value} onChange={(e) => onChange(e.target.value)} />;
    case "datetime":
      // Stored as "Y-m-d H:i"; the input works with "Y-m-dTH:i".
      return (
        <Input
          type="datetime-local"
          value={value.replace(" ", "T")}
          onChange={(e) => onChange(e.target.value.replace("T", " "))}
        />
      );
    case "number":
    case "money":
      return (
        <Input
          inputMode="decimal"
          value={value}
          onChange={(e) => onChange(e.target.value)}
          placeholder={column.type === "money" ? "֏" : undefined}
          className="tabular-nums"
        />
      );
    default:
      return <Input value={value} onChange={(e) => onChange(e.target.value)} />;
  }
}
