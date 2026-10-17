// Shared by server and client components of the business's own tables.
import type { AdminDataColumnDto, DataCellValue, DataColumnType, DataTableSection } from "@/lib/adminApi";

export const SECTIONS: Record<DataTableSection, { label: string; base: string; feature: "orders" | "sales" }> = {
  orders: { label: "Պատվերներ", base: "/admin/orders", feature: "orders" },
  sales: { label: "Վաճառքներ", base: "/admin/sales", feature: "sales" },
};

export function tablePath(section: DataTableSection, id?: number, suffix = ""): string {
  return id === undefined ? `${SECTIONS[section].base}/tables/new` : `${SECTIONS[section].base}/tables/${id}${suffix}`;
}

export const COLUMN_TYPES: DataColumnType[] = ["text", "number", "money", "date", "select", "boolean"];

export const COLUMN_TYPE_LABELS: Record<DataColumnType, string> = {
  text: "Տեքստ",
  number: "Թիվ",
  money: "Գումար (֏)",
  date: "Ամսաթիվ",
  select: "Ցանկից ընտրություն",
  boolean: "Այո / Ոչ",
};

/** How a stored value reads in the table. */
export function formatCell(value: DataCellValue | undefined, column: Pick<AdminDataColumnDto, "type">): string {
  if (value === null || value === undefined || value === "") return "—";

  switch (column.type) {
    case "money":
      return typeof value === "number" ? `${value.toLocaleString("hy-AM")} ֏` : String(value);
    case "number":
      return typeof value === "number" ? value.toLocaleString("hy-AM") : String(value);
    case "date": {
      const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(String(value));
      return match ? `${match[3]}.${match[2]}.${match[1]}` : String(value);
    }
    case "boolean":
      return value === true ? "Այո" : value === false ? "Ոչ" : String(value);
    default:
      return String(value);
  }
}

/** Laravel's 422 body: the first message per field. */
export function validationErrors(json: unknown): Record<string, string> {
  const errors = (json as { errors?: Record<string, string[]> } | null)?.errors ?? {};
  return Object.fromEntries(Object.entries(errors).map(([field, messages]) => [field, messages[0] ?? ""]));
}
