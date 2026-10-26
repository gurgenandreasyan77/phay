// Files the business's admin downloads: tables and the owner's reports as
// XLSX or CSV, made by the API for the request and passed through the
// admin's server (/api/admin/...). Links only: no share, no stored file.
import { Download } from "lucide-react";
import type { AssistantActionDto, PeriodKind } from "@/lib/adminApi";
import { buttonClasses, cn } from "@/ui";

export type FileFormat = "xlsx" | "csv";

export function tableDownloadHref(id: number, format: FileFormat = "xlsx"): string {
  return `/api/admin/data-tables/${id}/export${format === "csv" ? "?format=csv" : ""}`;
}

/** Several tables: one workbook, a sheet each. */
export function tablesDownloadHref(ids: number[]): string {
  return ids.length === 1 ? tableDownloadHref(ids[0]) : `/api/admin/data-tables/export?ids=${ids.join(",")}`;
}

export function reportDownloadHref(
  kind: "finance" | "expenses",
  by: PeriodKind,
  date: string | undefined,
  format: FileFormat = "xlsx",
): string {
  const query = new URLSearchParams({ by, ...(date ? { date } : {}), ...(format === "csv" ? { format } : {}) });
  return `/api/admin/${kind === "finance" ? "analytics/finance" : "expenses/statistics"}/export?${query}`;
}

/** Where a download card of the AI chat points. */
export function actionDownloadHref(action: Extract<AssistantActionDto, { type: "download" }>): string {
  if (action.kind === "tables") {
    const ids = action.ids ?? [];
    return ids.length === 1 ? tableDownloadHref(ids[0], action.format) : tablesDownloadHref(ids);
  }
  return reportDownloadHref(action.kind, action.by ?? "month", action.date, action.format);
}

/** «Excel» and «CSV» buttons for one file. */
export function DownloadLinks({
  href,
  className,
  label = "Ներբեռնել",
  size = "sm",
}: {
  href: (format: FileFormat) => string;
  className?: string;
  label?: string;
  /** md next to page actions, sm next to filters. */
  size?: "sm" | "md";
}) {
  return (
    <div className={cn("flex flex-wrap items-center gap-1", className)} role="group" aria-label={label}>
      <a href={href("xlsx")} download className={buttonClasses("secondary", size)} title={`${label} Excel (XLSX)`}>
        <Download className="h-4 w-4" />
        Excel
      </a>
      <a href={href("csv")} download className={buttonClasses("ghost", size)} title={`${label} CSV`}>
        CSV
      </a>
    </div>
  );
}
