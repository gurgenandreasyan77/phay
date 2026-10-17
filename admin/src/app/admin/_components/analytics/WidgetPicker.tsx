"use client";

import { useState } from "react";
import { useRouter } from "next/navigation";
import { LayoutGrid } from "lucide-react";
import type { DashboardWidgetId, DataTableSection, TableAnalyticsDto } from "@/lib/adminApi";
import { Button, Card, cn } from "../ui";
import { MISSING_LABELS, WIDGET_GROUPS, widgetLabels } from "./widgets";

/**
 * The catalog of a section's analytics: the owner switches figures, charts
 * and tables on and off. Ones the business's columns can't give are shown
 * with the column they need, so the owner knows what to add to the table.
 */
export function WidgetPicker({
  section,
  dashboard,
}: {
  section: DataTableSection;
  dashboard: TableAnalyticsDto["dashboard"];
}) {
  const router = useRouter();
  const [open, setOpen] = useState(false);
  const [chosen, setChosen] = useState<Set<DashboardWidgetId>>(() => new Set(dashboard.enabled));
  const [pending, setPending] = useState(false);
  const [failed, setFailed] = useState(false);

  const available = dashboard.widgets.filter((w) => w.missing.length === 0).length;

  function toggle(id: DashboardWidgetId) {
    setChosen((current) => {
      const next = new Set(current);
      if (next.has(id)) next.delete(id);
      else next.add(id);
      return next;
    });
  }

  async function save() {
    setPending(true);
    setFailed(false);
    const response = await fetch("/api/admin/analytics/dashboard", {
      method: "PUT",
      headers: { "Content-Type": "application/json" },
      // In catalog order, so the screen reads the same whatever was clicked first.
      body: JSON.stringify({ section, widgets: dashboard.widgets.map((w) => w.id).filter((id) => chosen.has(id)) }),
    }).catch(() => null);
    setPending(false);

    if (!response?.ok) {
      setFailed(true);
      return;
    }
    setOpen(false);
    router.refresh();
  }

  if (!open) {
    return (
      <Button
        variant="secondary"
        size="sm"
        onClick={() => {
          setChosen(new Set(dashboard.enabled));
          setOpen(true);
        }}
      >
        <LayoutGrid className="h-4 w-4" />
        Ավելացնել գրաֆիկ
      </Button>
    );
  }

  return (
    <Card className="w-full space-y-5">
      <div>
        <p className="font-semibold text-ink">Ինչ ցույց տալ վերլուծությունում</p>
        <p className="mt-1 text-sm text-ink-muted">
          Ձեր տվյալներով հասանելի է {available} ցուցանիշ {dashboard.widgets.length}-ից։ Ընտրեք նրանք, որոնք ձեզ պետք են․
          AI օգնականը նույնպես կտեսնի միայն ընտրվածները։
        </p>
      </div>

      {WIDGET_GROUPS.map((group) => (
        <div key={group.id} className="space-y-2">
          <p className="text-xs font-medium uppercase tracking-wide text-ink-faint">{group.label}</p>
          <div className="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
            {dashboard.widgets
              .filter((w) => w.group === group.id)
              .map((widget) => {
                const labels = widgetLabels(widget.id, section);
                const off = widget.missing.length > 0;
                return (
                  <label
                    key={widget.id}
                    className={cn(
                      "flex gap-3 rounded-md border p-3 text-sm",
                      off
                        ? "cursor-not-allowed border-border bg-surface-secondary/50"
                        : chosen.has(widget.id)
                          ? "cursor-pointer border-primary bg-primary-light"
                          : "cursor-pointer border-border hover:border-primary/50",
                    )}
                  >
                    <input
                      type="checkbox"
                      className="mt-0.5"
                      disabled={off}
                      checked={!off && chosen.has(widget.id)}
                      onChange={() => toggle(widget.id)}
                    />
                    <span className="min-w-0">
                      <span className={cn("block font-medium", off ? "text-ink-faint" : "text-ink")}>{labels.hy}</span>
                      <span className="block text-xs text-ink-faint">
                        {labels.ru} · {labels.en}
                      </span>
                      <span className={cn("mt-1 block text-xs", off ? "text-warning" : "text-ink-muted")}>
                        {off ? `Պետք է՝ ${widget.missing.map((m) => MISSING_LABELS[m] ?? m).join(", ")}` : labels.hint}
                      </span>
                    </span>
                  </label>
                );
              })}
          </div>
        </div>
      ))}

      <div className="flex flex-wrap items-center gap-3">
        <Button onClick={save} disabled={pending}>
          Պահպանել ({[...chosen].filter((id) => dashboard.widgets.some((w) => w.id === id && w.missing.length === 0)).length})
        </Button>
        <Button variant="ghost" onClick={() => setOpen(false)} disabled={pending}>
          Չեղարկել
        </Button>
        {failed ? <span className="text-sm text-danger">Չհաջողվեց պահպանել։</span> : null}
      </div>
    </Card>
  );
}
