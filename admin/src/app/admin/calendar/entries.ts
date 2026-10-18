// What the calendar shows of a row: read by the columns' roles, whatever
// the business called them. Shared by its server and client components.
import type { AdminDataTableDto, CalendarDto, CalendarLeadDto, CalendarRowDto, ColumnRole } from "@/lib/adminApi";
import { formatCell } from "../_components/tables/shared";

export type CalendarEntry = CalendarRowDto & {
  table: AdminDataTableDto;
  /** The service (or, without one, the row's first text), shown as its name. */
  title: string;
  customer: string | null;
  phone: string | null;
  status: string | null;
  address: string | null;
};

export type CalendarDay = {
  entries: CalendarEntry[];
  leads: CalendarLeadDto[];
  /** Money of the day's rows. */
  amount: number;
};

function cell(row: CalendarRowDto, table: AdminDataTableDto, role: ColumnRole): string | null {
  const column = table.columns.find((c) => c.role === role);
  if (!column) return null;
  const text = formatCell(row.values[column.key], column);
  return text === "—" ? null : text;
}

export function toEntry(row: CalendarRowDto, table: AdminDataTableDto): CalendarEntry {
  const fallback = table.columns.find((c) => c.type === "text" || c.type === "select");
  const title =
    cell(row, table, "item") ??
    (fallback ? formatCell(row.values[fallback.key], fallback) : "—");

  return {
    ...row,
    table,
    title: title === "—" ? table.name : title,
    customer: cell(row, table, "customer"),
    phone: cell(row, table, "phone"),
    status: cell(row, table, "status"),
    address: cell(row, table, "address"),
  };
}

/** The calendar's rows and site orders, by day. */
export function groupByDay(data: CalendarDto): Map<string, CalendarDay> {
  const tables = new Map(data.tables.map((t) => [t.id, t]));
  const days = new Map<string, CalendarDay>();
  const dayOf = (date: string) => {
    let day = days.get(date);
    if (!day) days.set(date, (day = { entries: [], leads: [], amount: 0 }));
    return day;
  };

  for (const row of data.rows) {
    const table = tables.get(row.tableId);
    if (!table) continue;
    const day = dayOf(row.date);
    day.entries.push(toEntry(row, table));
    day.amount += row.amount ?? 0;
  }
  for (const lead of data.leads) dayOf(lead.date).leads.push(lead);

  return days;
}

/** The colors of the tables, so a day shows which table an order is in. */
export const TABLE_COLORS = ["bg-chart-1", "bg-chart-3", "bg-chart-6", "bg-chart-4", "bg-chart-5", "bg-chart-2"];

export function tableColor(data: Pick<CalendarDto, "tables">, tableId: number): string {
  const index = data.tables.findIndex((t) => t.id === tableId);
  return TABLE_COLORS[(index < 0 ? 0 : index) % TABLE_COLORS.length];
}
