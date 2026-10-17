// Shared by server and client components of the business's own tables.
import type { AdminDataColumnDto, DataCellValue, DataColumnType, DataTableSection } from "@/lib/adminApi";

export const SECTIONS: Record<DataTableSection, { label: string; base: string; feature: "orders" | "sales" }> = {
  orders: { label: "Պատվերներ", base: "/admin/orders", feature: "orders" },
  sales: { label: "Վաճառքներ", base: "/admin/sales", feature: "sales" },
};

export function tablePath(section: DataTableSection, id?: number, suffix = ""): string {
  return id === undefined ? `${SECTIONS[section].base}/tables/new` : `${SECTIONS[section].base}/tables/${id}${suffix}`;
}

export function tablesPath(section: DataTableSection): string {
  return section === "orders" ? `${SECTIONS.orders.base}/tables` : SECTIONS.sales.base;
}

export const COLUMN_TYPES: DataColumnType[] = ["text", "number", "money", "date", "datetime", "select", "boolean"];

export const COLUMN_TYPE_LABELS: Record<DataColumnType, string> = {
  text: "Տեքստ",
  number: "Թիվ",
  money: "Գումար (֏)",
  date: "Ամսաթիվ",
  datetime: "Ամսաթիվ և ժամ",
  select: "Ցանկից ընտրություն",
  boolean: "Այո / Ոչ",
};

/** How a stored value reads in the table. */
export function formatCell(value: DataCellValue | undefined, column: Pick<AdminDataColumnDto, "type">): string {
  if (value === null || value === undefined || value === "") return "—";

  switch (column.type) {
    case "money":
      return typeof value === "number" ? formatMoney(value) : String(value);
    case "number":
      return typeof value === "number" ? formatNumber(value) : String(value);
    case "date":
    case "datetime":
      return formatMoment(String(value));
    case "boolean":
      return value === true ? "Այո" : value === false ? "Ոչ" : String(value);
    default:
      return String(value);
  }
}

/** "2026-10-17" → "17.10.2026", "2026-10-17 14:35" → "17.10.2026 14:35". */
export function formatMoment(value: string): string {
  const match = /^(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{2}:\d{2}))?/.exec(value);
  if (!match) return value;
  const date = `${match[3]}.${match[2]}.${match[1]}`;
  return match[4] && match[4] !== "00:00" ? `${date} ${match[4]}` : date;
}

/**
 * An ISO time as "17.10.2026 18:27" in Yerevan time. Not toLocaleString():
 * the server and the browser format it differently, which breaks hydration.
 */
export function formatDateTime(iso: string | null | undefined): string {
  if (!iso) return "—";
  const parts = Object.fromEntries(
    new Intl.DateTimeFormat("en-GB", {
      timeZone: "Asia/Yerevan",
      year: "numeric",
      month: "2-digit",
      day: "2-digit",
      hour: "2-digit",
      minute: "2-digit",
      hour12: false,
    })
      .formatToParts(new Date(iso))
      .map((part) => [part.type, part.value]),
  );
  return `${parts.day}.${parts.month}.${parts.year} ${parts.hour}:${parts.minute}`;
}

/**
 * 12500.5 → "12 500,5", the Armenian way. By hand: Node on the server may
 * lack the hy-AM locale and print "12,500.5", which breaks hydration.
 */
export function formatNumber(value: number): string {
  const [whole, fraction] = String(Math.round(value * 100) / 100).split(".");
  const grouped = whole.replace(/\B(?=(\d{3})+(?!\d))/g, "\u00a0");
  return fraction ? `${grouped},${fraction}` : grouped;
}

export function formatMoney(value: number | null | undefined): string {
  return value === null || value === undefined ? "—" : `${formatNumber(value)} ֏`;
}

/** Laravel's 422 body: the first message per field. */
export function validationErrors(json: unknown): Record<string, string> {
  const errors = (json as { errors?: Record<string, string[]> } | null)?.errors ?? {};
  return Object.fromEntries(Object.entries(errors).map(([field, messages]) => [field, messages[0] ?? ""]));
}
