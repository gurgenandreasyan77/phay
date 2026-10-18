"use client";

import { useState } from "react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { Phone, Plus, Trash2 } from "lucide-react";
import type { AdminDataTableDto, CalendarLeadDto } from "@/lib/adminApi";
import { Badge, Button, buttonClasses, Card, cn, FieldLabel, Select } from "../_components/ui";
import { LEAD_STATUS_LABELS, LEAD_STATUS_TONES } from "../_components/leadStatus";
import { bucketTitle } from "../_components/analytics/BarChart";
import { CellInput, fromDraft, toDraft, type Draft } from "../_components/tables/RowsGrid";
import { formatMoney, SECTIONS, tablePath, validationErrors } from "../_components/tables/shared";
import type { CalendarEntry } from "./entries";

/** The table a new order went to last time, remembered in this browser. */
const LAST_TABLE_KEY = "servio.calendar.table";

function lastTable(tables: AdminDataTableDto[]): number | undefined {
  try {
    const id = Number(localStorage.getItem(LAST_TABLE_KEY));
    return tables.some((t) => t.id === id) ? id : undefined;
  } catch {
    return undefined;
  }
}

/** One day of the calendar: its orders, adding one to a table and deleting one. */
export function DayPanel({
  day,
  title,
  tables,
  months,
  entries,
  leads,
  colors,
  canDesign,
}: {
  day: string;
  title: string;
  tables: AdminDataTableDto[];
  months: Record<number, string>;
  entries: CalendarEntry[];
  leads: CalendarLeadDto[];
  colors: Record<number, string>;
  canDesign: boolean;
}) {
  const router = useRouter();
  const [adding, setAdding] = useState(false);
  const [deleting, setDeleting] = useState<number | null>(null);
  const total = entries.reduce((sum, e) => sum + (e.amount ?? 0), 0);

  async function remove(entry: CalendarEntry) {
    if (!confirm(`Ջնջե՞լ «${entry.title}» պատվերը «${entry.table.name}» աղյուսակից։ Այն կհեռացվի նաև վերլուծությունից։`)) return;
    setDeleting(entry.id);
    try {
      const response = await fetch(`/api/admin/data-tables/${entry.tableId}/rows/${entry.id}`, { method: "DELETE" });
      if (response.ok) router.refresh();
    } finally {
      setDeleting(null);
    }
  }

  return (
    <Card className="space-y-4 self-start lg:sticky lg:top-4">
      <div className="flex items-start justify-between gap-3">
        <div>
          <h3 className="font-semibold text-ink first-letter:uppercase">{title}</h3>
          <p className="mt-0.5 text-sm text-ink-muted">
            {entries.length + leads.length} պատվեր{total > 0 ? ` · ${formatMoney(total)}` : ""}
          </p>
        </div>
        {tables.length > 0 && !adding ? (
          <Button size="sm" onClick={() => setAdding(true)}>
            <Plus className="h-4 w-4" />
            Ավելացնել
          </Button>
        ) : null}
      </div>

      {adding ? (
        <NewOrderForm
          day={day}
          tables={tables}
          months={months}
          onDone={(saved) => {
            setAdding(false);
            if (saved) router.refresh();
          }}
        />
      ) : null}

      {tables.length === 0 ? (
        <p className="rounded-md bg-surface-secondary px-3 py-2 text-sm text-ink-muted">
          Օրացույցից պատվեր ավելացնելու համար պետք է պատվերների աղյուսակ՝ «Ամսաթիվ» դաշտով։{" "}
          {canDesign ? (
            <Link href={tablePath("orders")} className="font-medium text-primary underline">
              Ստեղծել աղյուսակ
            </Link>
          ) : null}
        </p>
      ) : null}

      {entries.length === 0 && leads.length === 0 && !adding ? (
        <p className="py-6 text-center text-sm text-ink-muted">Այս օրը պատվերներ չկան։</p>
      ) : null}

      <ul className="divide-y divide-border">
        {entries.map((entry) => (
          <li key={entry.id} className="flex items-start gap-3 py-3">
            <span className="w-11 shrink-0 pt-0.5 text-sm font-medium text-ink">{entry.time ?? "—"}</span>
            <div className="min-w-0 flex-1">
              <p className="break-words font-medium text-ink">{entry.title}</p>
              {entry.customer || entry.phone ? (
                <p className="mt-0.5 flex flex-wrap items-center gap-x-2 text-sm text-ink-muted">
                  {entry.customer ? <span>{entry.customer}</span> : null}
                  {entry.phone ? (
                    <a href={`tel:${entry.phone.replace(/[^\d+]/g, "")}`} className="inline-flex items-center gap-1 hover:text-ink">
                      <Phone className="h-3 w-3" />
                      {entry.phone}
                    </a>
                  ) : null}
                </p>
              ) : null}
              {entry.address ? <p className="mt-0.5 text-sm text-ink-muted">{entry.address}</p> : null}
              <p className="mt-1 flex flex-wrap items-center gap-2 text-xs text-ink-muted">
                <Link href={tablePath("orders", entry.tableId)} className="inline-flex items-center gap-1 hover:text-ink">
                  <span className={cn("h-2 w-2 rounded-full", colors[entry.tableId])} />
                  {entry.table.name}
                </Link>
                {entry.status ? <Badge>{entry.status}</Badge> : null}
              </p>
            </div>
            <div className="flex shrink-0 flex-col items-end gap-1">
              {entry.amount !== null ? <span className="whitespace-nowrap text-sm font-medium text-ink">{formatMoney(entry.amount)}</span> : null}
              <Button
                variant="ghost"
                size="sm"
                aria-label="Ջնջել"
                disabled={deleting === entry.id}
                onClick={() => remove(entry)}
              >
                <Trash2 className="h-4 w-4" />
              </Button>
            </div>
          </li>
        ))}
        {leads.map((lead) => (
          <li key={`lead-${lead.id}`} className="flex items-start gap-3 py-3">
            <span className="w-11 shrink-0 pt-0.5 text-sm text-info">Կայք</span>
            <div className="min-w-0 flex-1">
              <p className="break-words font-medium text-ink">{lead.service ?? "Պատվեր կայքից"}</p>
              <p className="mt-0.5 flex flex-wrap items-center gap-x-2 text-sm text-ink-muted">
                <span>{lead.name}</span>
                <a href={`tel:${lead.phone.replace(/[^\d+]/g, "")}`} className="inline-flex items-center gap-1 hover:text-ink">
                  <Phone className="h-3 w-3" />
                  {lead.phone}
                </a>
              </p>
              <p className="mt-1 flex flex-wrap items-center gap-2 text-xs">
                <Badge tone={LEAD_STATUS_TONES[lead.status]}>{LEAD_STATUS_LABELS[lead.status]}</Badge>
                <Link href={`${SECTIONS.orders.base}/site`} className="text-ink-muted hover:text-ink">
                  Կայքի պատվերներ →
                </Link>
              </p>
            </div>
            {lead.finalPrice !== null ? (
              <span className="shrink-0 whitespace-nowrap text-sm font-medium text-ink">{formatMoney(lead.finalPrice)}</span>
            ) : null}
          </li>
        ))}
      </ul>
    </Card>
  );
}

/**
 * A new row of the chosen table, on this day: the table's own fields. When
 * the chosen table is kept for another month, the API puts the row in the
 * table of the date's month, made like the chosen one when there is none.
 */
function NewOrderForm({
  day,
  tables,
  months,
  onDone,
}: {
  day: string;
  tables: AdminDataTableDto[];
  months: Record<number, string>;
  onDone: (saved: boolean) => void;
}) {
  const [tableId, setTableId] = useState<number>(
    () => tables.find((t) => months[t.id] === day.slice(0, 7))?.id ?? lastTable(tables) ?? tables[0].id,
  );
  const table = tables.find((t) => t.id === tableId) ?? tables[0];
  const [draft, setDraft] = useState<Draft>(() => toDraft(table, undefined, day));
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [pending, setPending] = useState(false);
  const [failed, setFailed] = useState(false);

  // Where the row will go, by the month of the date typed in.
  const month = table.dateColumn ? (draft[table.dateColumn] ?? "").slice(0, 7) : "";
  const elsewhere = /^\d{4}-\d{2}$/.test(month) && months[table.id] !== undefined && months[table.id] !== month;
  const target = elsewhere ? tables.find((t) => months[t.id] === month) : undefined;

  function chooseTable(id: number) {
    const next = tables.find((t) => t.id === id);
    if (!next) return;
    setTableId(id);
    setDraft(toDraft(next, undefined, day));
    setErrors({});
  }

  async function save() {
    setPending(true);
    setErrors({});
    setFailed(false);
    try {
      const response = await fetch("/api/admin/calendar/rows", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ table: table.id, values: fromDraft(table, draft) }),
      });
      if (response.status === 422) {
        setErrors(validationErrors(await response.json().catch(() => null)));
        return;
      }
      if (!response.ok) {
        setFailed(true);
        return;
      }
      try {
        localStorage.setItem(LAST_TABLE_KEY, String(table.id));
      } catch {
        // Only a convenience.
      }
      onDone(true);
    } catch {
      setFailed(true);
    } finally {
      setPending(false);
    }
  }

  return (
    <form
      className="space-y-3 rounded-md border border-primary/40 bg-primary-light/40 p-3"
      onSubmit={(e) => {
        e.preventDefault();
        save();
      }}
    >
      {tables.length > 1 ? (
        <label className="block space-y-1">
          <FieldLabel>Աղյուսակ</FieldLabel>
          <Select value={table.id} onChange={(e) => chooseTable(Number(e.target.value))}>
            {tables.map((t) => (
              <option key={t.id} value={t.id}>
                {t.name}
              </option>
            ))}
          </Select>
        </label>
      ) : (
        <p className="text-xs text-ink-muted">Աղյուսակ՝ {table.name}</p>
      )}

      {table.columns.map((column) => (
        <label key={column.key} className="block space-y-1">
          <FieldLabel>
            {column.label}
            {column.required || column.key === table.dateColumn ? <span className="text-danger"> *</span> : null}
          </FieldLabel>
          <CellInput
            column={column}
            value={draft[column.key] ?? ""}
            onChange={(value) => setDraft((d) => ({ ...d, [column.key]: value }))}
          />
          {errors[`values.${column.key}`] ? <p className="text-xs text-danger">{errors[`values.${column.key}`]}</p> : null}
        </label>
      ))}

      {elsewhere ? (
        <p className="rounded-md bg-surface px-3 py-2 text-xs text-ink-muted">
          {target
            ? `Ամսաթիվն այլ ամսից է։ Պատվերը կավելացվի «${target.name}» աղյուսակում։`
            : `${bucketTitle(month)}-ի համար աղյուսակ դեռ չկա։ Կստեղծվի նորը՝ «${table.name}»-ի սյունակներով, և պատվերը կավելացվի այնտեղ։`}
        </p>
      ) : null}

      <div className="flex items-center gap-2">
        <Button type="submit" size="sm" disabled={pending}>
          Պահպանել
        </Button>
        <button type="button" className={buttonClasses("ghost", "sm")} onClick={() => onDone(false)} disabled={pending}>
          Չեղարկել
        </button>
        {failed ? <span className="text-xs text-danger">Չհաջողվեց պահպանել։</span> : null}
      </div>
    </form>
  );
}
