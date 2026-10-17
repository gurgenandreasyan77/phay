import Link from "next/link";
import { cn } from "../ui";
import { SECTIONS, tablesPath } from "./shared";

/**
 * The tabs on top of the orders section: the orders that came from the site,
 * and the list of the business's own order tables (however many it has).
 */
export function OrdersTabs({ active, tablesCount }: { active: "site" | "tables"; tablesCount: number }) {
  const tabs = [
    { key: "site", href: SECTIONS.orders.base, label: "Կայքից" },
    { key: "tables", href: tablesPath("orders"), label: tablesCount > 0 ? `Աղյուսակներ (${tablesCount})` : "Աղյուսակներ" },
  ];

  return (
    <div className="flex flex-wrap items-center gap-1 border-b border-border">
      {tabs.map((tab) => (
        <Link
          key={tab.key}
          href={tab.href}
          className={cn(
            "-mb-px border-b-2 px-3 py-2 text-sm transition-colors",
            active === tab.key ? "border-primary font-medium text-ink" : "border-transparent text-ink-muted hover:text-ink",
          )}
        >
          {tab.label}
        </Link>
      ))}
    </div>
  );
}
