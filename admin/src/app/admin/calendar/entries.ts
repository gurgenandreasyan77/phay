// What the calendar shows of a row: read by the columns' roles, whatever
// the business called them. Shared by its server and client components.
import type {
  AdminDataTableDto,
  CalendarDto,
  CalendarGoogleEventDto,
  CalendarLeadDto,
  CalendarRowDto,
  ColumnRole,
} from "@/lib/adminApi";
import { formatCell, formatNumber } from "../_components/tables/shared";

export type CalendarEntry = CalendarRowDto & {
  table: AdminDataTableDto;
  /** The service or product (or, without one, the row's first text), shown as its name. */
  title: string;
  customer: string | null;
  phone: string | null;
  status: string | null;
  address: string | null;
  /** A sale's "2 հատ" (quantity and unit), its model or other details. */
  quantity: string | null;
  details: string | null;
};

/** Services done and goods sold on a day: how many and for how much. */
export type SectionTotal = { count: number; amount: number };

export type CalendarDay = {
  /** Rows of the order tables (services) and of the sales tables, apart. */
  services: CalendarEntry[];
  sales: CalendarEntry[];
  leads: CalendarLeadDto[];
  /** Events of the synced Google calendars, not in a table yet. */
  google: CalendarGoogleEventDto[];
  /** Money of the day's rows and completed site orders. */
  amount: number;
  totals: { services: SectionTotal; sales: SectionTotal };
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
    cell(row, table, "model") ??
    (fallback ? formatCell(row.values[fallback.key], fallback) : "—");
  const quantityColumn = table.columns.find((c) => c.role === "quantity");
  const quantity = quantityColumn ? row.values[quantityColumn.key] : null;
  const unit = cell(row, table, "unit");
  const details = (["model", "brand", "size", "color", "category"] as ColumnRole[])
    .map((role) => cell(row, table, role))
    .filter((v): v is string => v !== null && v !== title);

  return {
    ...row,
    table,
    title: title === "—" ? table.name : title,
    customer: cell(row, table, "customer"),
    phone: cell(row, table, "phone"),
    status: cell(row, table, "status"),
    address: cell(row, table, "address"),
    quantity: typeof quantity === "number" ? `${formatNumber(quantity)}${unit ? ` ${unit}` : ""}` : null,
    details: details.length > 0 ? details.join(" · ") : null,
  };
}

function emptyDay(): CalendarDay {
  return {
    services: [],
    sales: [],
    leads: [],
    google: [],
    amount: 0,
    totals: { services: { count: 0, amount: 0 }, sales: { count: 0, amount: 0 } },
  };
}

/** The calendar's rows and site orders, by day. Site orders are services. */
export function groupByDay(data: CalendarDto): Map<string, CalendarDay> {
  const tables = new Map(data.tables.map((t) => [t.id, t]));
  const days = new Map<string, CalendarDay>();
  const dayOf = (date: string) => {
    let day = days.get(date);
    if (!day) days.set(date, (day = emptyDay()));
    return day;
  };

  for (const row of data.rows) {
    const table = tables.get(row.tableId);
    if (!table) continue;
    const day = dayOf(row.date);
    const kind = (row.section ?? table.section) === "sales" ? "sales" : "services";
    day[kind].push(toEntry(row, table));
    day.totals[kind].count += 1;
    day.totals[kind].amount += row.amount ?? 0;
    day.amount += row.amount ?? 0;
  }
  for (const lead of data.leads) {
    const day = dayOf(lead.date);
    day.leads.push(lead);
    day.totals.services.count += 1;
  }
  for (const event of data.google ?? []) dayOf(event.date).google.push(event);

  return days;
}

/**
 * The colours of the tables, so a day shows which table a row is in:
 * services in greens and blues as round dots, sales in ambers as small
 * squares, so the two never look alike.
 */
export const TABLE_COLORS = ["bg-chart-1", "bg-chart-6", "bg-chart-2", "bg-chart-4", "bg-chart-5"];
export const SALE_COLORS = ["bg-chart-3", "bg-chart-3/60", "bg-warning/40"];

export function tableColor(data: Pick<CalendarDto, "tables">, tableId: number): string {
  const table = data.tables.find((t) => t.id === tableId);
  const same = data.tables.filter((t) => (t.section === "sales") === (table?.section === "sales"));
  const index = Math.max(0, same.findIndex((t) => t.id === tableId));
  const palette = table?.section === "sales" ? SALE_COLORS : TABLE_COLORS;
  return palette[index % palette.length];
}

/** The mark of a table's row: a dot for a service, a little square for a sale. */
export function markShape(section: "orders" | "sales" | string): string {
  return section === "sales" ? "rounded-[2px]" : "rounded-full";
}

/** A Google event's mark: a hollow dot in Google's blue, unlike the tables' filled ones. */
export const GOOGLE_DOT = "border-[1.5px] border-[#1a73e8] bg-surface";
