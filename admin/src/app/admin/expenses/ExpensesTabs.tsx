import Link from "next/link";
import { BarChart3, Wallet } from "lucide-react";
import { cn } from "../_components/ui";

/** The expenses section's two screens: the expenses themselves and their statistics. */
export function ExpensesTabs({ active }: { active: "list" | "statistics" }) {
  const tabs = [
    { id: "list", href: "/admin/expenses", label: "Ծախսեր", icon: Wallet },
    { id: "statistics", href: "/admin/expenses/statistics", label: "Ծախսերի վիճակագրություն", icon: BarChart3 },
  ] as const;

  return (
    <nav className="flex flex-wrap gap-1 rounded-md border border-border bg-surface p-1" aria-label="Ծախսեր">
      {tabs.map((tab) => (
        <Link
          key={tab.id}
          href={tab.href}
          className={cn(
            "inline-flex items-center gap-1.5 rounded px-3 py-1.5 text-sm font-medium",
            active === tab.id ? "bg-primary text-primary-foreground" : "text-ink-muted hover:text-ink",
          )}
        >
          <tab.icon className="h-4 w-4" />
          {tab.label}
        </Link>
      ))}
    </nav>
  );
}
