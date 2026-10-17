import Link from "next/link";
import type { AdminDataTableDto, DataTableSection } from "@/lib/adminApi";
import { cn } from "../ui";
import { SECTIONS, tablePath } from "./shared";

/**
 * The tabs on top of the orders and sales sections: (for orders) the orders
 * that came from the site, then each table the business made itself.
 */
export function SectionTabs({
  section,
  tables,
  active,
  canDesign,
}: {
  section: DataTableSection;
  tables: AdminDataTableDto[];
  /** "home" for the section's own page, or a table id. */
  active: "home" | number | "new";
  canDesign: boolean;
}) {
  const tabs = [
    ...(section === "orders" ? [{ key: "home" as const, href: SECTIONS.orders.base, label: "Կայքից" }] : []),
    ...tables.map((table) => ({ key: table.id, href: tablePath(section, table.id), label: table.name })),
  ];

  return (
    <div className="flex flex-wrap items-center gap-1 border-b border-border">
      {tabs.map((tab) => (
        <Link
          key={tab.key}
          href={tab.href}
          className={cn(
            "-mb-px border-b-2 px-3 py-2 text-sm transition-colors",
            active === tab.key
              ? "border-primary font-medium text-ink"
              : "border-transparent text-ink-muted hover:text-ink",
          )}
        >
          {tab.label}
        </Link>
      ))}
      {canDesign ? (
        <Link
          href={tablePath(section)}
          className={cn(
            "-mb-px border-b-2 px-3 py-2 text-sm transition-colors",
            active === "new" ? "border-primary font-medium text-ink" : "border-transparent text-primary hover:text-primary-hover",
          )}
        >
          + Նոր աղյուսակ
        </Link>
      ) : null}
    </div>
  );
}
