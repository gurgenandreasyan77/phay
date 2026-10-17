"use client";

import { useState } from "react";
import { usePathname, useRouter, useSearchParams } from "next/navigation";
import { Pencil, Trash2 } from "lucide-react";
import type { AdminDataTableDto, AdminDataTableRowDto, DataCellValue } from "@/lib/adminApi";
import { Badge, Button, EmptyRow, Input, Select, TableWrap, Td, Th, Thead, Tr } from "../ui";
import { formatCell, validationErrors } from "./shared";

type Column = AdminDataTableDto["columns"][number];

/** What the inputs hold while a row is edited: always strings. */
type Draft = Record<string, string>;

function toDraft(table: AdminDataTableDto, row?: AdminDataTableRowDto): Draft {
  return Object.fromEntries(
    table.columns.map((column) => {
      const value = row?.values[column.key];
      return [column.key, value === null || value === undefined ? "" : String(value)];
    }),
  );
}

/** Strings to what the API takes; it parses and validates each value by type. */
function fromDraft(table: AdminDataTableDto, draft: Draft): Record<string, DataCellValue> {
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
              {column.required ? <span className="text-danger"> *</span> : null}
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
              {searchParams.get("search") ? "Ոչինչ չի գտնվել։" : "Տողեր դեռ չկան։ Ավելացրեք առաջինը։"}
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

function CellInput({ column, value, onChange }: { column: Column; value: string; onChange: (value: string) => void }) {
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
    case "number":
    case "money":
      return (
        <Input
          inputMode="decimal"
          value={value}
          onChange={(e) => onChange(e.target.value)}
          placeholder={column.type === "money" ? "֏" : undefined}
          className="text-right"
        />
      );
    default:
      return <Input value={value} onChange={(e) => onChange(e.target.value)} />;
  }
}
