"use client";

import { Download, FileSpreadsheet } from "lucide-react";
import type { AssistantActionDto } from "@/lib/adminApi";
import { buttonClasses } from "@/ui";
import { actionDownloadHref } from "../_components/downloads/downloads";
import { ReshapeCard } from "../_components/tables/Reshape";

/**
 * Cards under an answer: a reshape of tables the AI prepared (applied only
 * with its button) and files to download. The AI never changes the tables
 * itself.
 */
export function AnswerActions({ actions }: { actions: AssistantActionDto[] }) {
  if (actions.length === 0) return null;

  return (
    <div className="mt-2 space-y-2">
      {actions.map((action, i) =>
        action.type === "reshape" ? (
          <ReshapeCard key={`r-${action.id}`} id={action.id} />
        ) : (
          <div key={`d-${i}`} className="flex flex-wrap items-center gap-3 rounded-xl border border-border bg-surface p-3">
            <span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-success-light text-success">
              <FileSpreadsheet className="h-5 w-5" strokeWidth={1.8} />
            </span>
            <span className="min-w-0 flex-1 text-sm">
              <span className="block truncate font-medium text-ink">{action.label}</span>
              <span className="text-xs text-ink-muted">{action.format === "csv" ? "CSV" : "Excel (XLSX)"}</span>
            </span>
            <a href={actionDownloadHref(action)} download className={buttonClasses("secondary", "sm")}>
              <Download className="h-4 w-4" />
              Ներբեռնել
            </a>
          </div>
        ),
      )}
    </div>
  );
}
