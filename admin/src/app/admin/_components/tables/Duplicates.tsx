"use client";

import { useMemo, useState } from "react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { CalendarDays, Copy, Trash2 } from "lucide-react";
import type { DataCellValue, DuplicateGroupDto, DuplicateRowDto } from "@/lib/adminApi";
import { Badge, Button, Card, cn, EmptyCard } from "@/ui";
import { ConfirmDialog, type ConfirmContent } from "@/ui/dialog/ConfirmDialog";
import { formatMoment, formatMoney, SECTIONS, tablePath } from "./shared";

function text(value: DataCellValue | undefined): string {
  if (value === null || value === undefined || value === "") return "—";
  if (typeof value === "boolean") return value ? "Այո" : "Ոչ";
  return String(value);
}

/** The labels shown for a group: every field any of its rows has. */
function labelsOf(group: DuplicateGroupDto): string[] {
  const labels: string[] = [];
  for (const row of group.rows) {
    for (const [label, value] of Object.entries(row.values)) {
      if (value !== null && value !== "" && !labels.includes(label)) labels.push(label);
    }
  }
  return labels;
}

type Asking =
  | { kind: "keep"; group: DuplicateGroupDto }
  | { kind: "delete"; group: DuplicateGroupDto; row: DuplicateRowDto }
  | { kind: "copies"; table: string; ids: number[]; groups: string[] };

/**
 * Tables holding exact copies (rows already counted elsewhere): a whole
 * table copied once is many groups, so its copies go at once.
 */
function copiesByTable(groups: DuplicateGroupDto[]): { table: string; tableId: number; ids: number[]; groups: string[] }[] {
  const byTable = new Map<number, { table: string; tableId: number; ids: number[]; groups: string[] }>();
  for (const group of groups) {
    for (const row of group.rows) {
      if (row.copyOf === null || row.source === "google") continue;
      const entry = byTable.get(row.tableId) ?? { table: row.table, tableId: row.tableId, ids: [], groups: [] };
      entry.ids.push(row.id);
      // The group is settled when every other row of it is a copy of the one kept.
      if (group.rows.filter((r) => r.copyOf === null).length === 1) entry.groups.push(group.key);
      byTable.set(row.tableId, entry);
    }
  }
  return [...byTable.values()].sort((a, b) => b.ids.length - a.ids.length);
}

/**
 * The same order, sale or expense found in several tables: each group side
 * by side, and the owner's answer. «Keep all»: they are different records
 * (counted apart, never asked again). «Delete»: that copy goes, the others
 * stay. An exact copy is already counted once meanwhile; a possible one (a
 * field missing on one side) counts twice until answered.
 */
export function DuplicatesReview({ groups }: { groups: DuplicateGroupDto[] }) {
  const router = useRouter();
  const [asking, setAsking] = useState<Asking | null>(null);
  const [pending, setPending] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [done, setDone] = useState<Set<string>>(new Set());

  const shown = useMemo(() => groups.filter((g) => !done.has(g.key)), [groups, done]);

  async function answer() {
    if (!asking) return;
    setPending(true);
    setError(null);
    const ids = asking.kind === "keep" ? asking.group.rows.map((r) => r.id) : asking.kind === "copies" ? asking.ids : [asking.row.id];
    const response = await fetch(`/api/admin/duplicates/${asking.kind === "keep" ? "keep" : "delete"}`, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ ids }),
    }).catch(() => null);
    setPending(false);
    if (!response?.ok) {
      setError("Չհաջողվեց։ Փորձեք կրկին։");
      return;
    }
    setDone((current) => {
      const next = new Set(current);
      for (const key of asking.kind === "copies" ? asking.groups : [asking.group.key]) next.add(key);
      return next;
    });
    setAsking(null);
    router.refresh();
  }

  if (shown.length === 0) {
    return <EmptyCard>Կրկնվող գրառումներ չկան․ ամեն պատվեր հաշվվում է մեկ անգամ։</EmptyCard>;
  }

  const bulk = copiesByTable(shown).filter((t) => t.ids.length > 1);

  const confirm: ConfirmContent | null = !asking
    ? null
    : asking.kind === "copies"
      ? {
          title: `Ջնջե՞լ «${asking.table}»-ի կրկնօրինակները`,
          description:
            "Կջնջվեն միայն այն տողերը, որոնք ճիշտ նույնն են մեկ այլ աղյուսակի տողի հետ (այժմ արդեն չեն հաշվվում)։ Բնօրինակները կմնան։ Սա հնարավոր չէ հետ բերել։",
          details: [{ label: "Տողեր", value: String(asking.ids.length) }],
          confirmLabel: "Ջնջել կրկնօրինակները",
          tone: "danger",
        }
    : asking.kind === "keep"
      ? {
          title: "Թողնե՞լ բոլորը",
          description:
            "Սրանք տարբեր գրառումներ են. բոլորը կհաշվվեն վերլուծության մեջ և օրացույցում, և այլևս չենք հարցնի դրանց մասին։",
          details: asking.group.rows.map((r) => ({ label: r.table, value: r.amount !== null ? formatMoney(r.amount) : text(Object.values(r.values)[0]) })),
          confirmLabel: "Թողնել բոլորը",
        }
      : {
          title: "Ջնջե՞լ այս տողը",
          description:
            asking.row.source === "google"
              ? "Այս տողը Google օրացույցից է․ եթե այն ջնջեք, Google-ից հաջորդ թարմացման ժամանակ այն կարող է վերադառնալ։ Ավելի լավ է ջնջել մյուս պատճենը։"
              : "Տողը կջնջվի իր աղյուսակից, մյուսները կմնան։ Սա հնարավոր չէ հետ բերել։",
          details: [
            { label: "Աղյուսակ", value: asking.row.table },
            { label: "Օր", value: formatMoment(asking.group.day) + (asking.row.time ? ` ${asking.row.time}` : "") },
            ...(asking.row.amount !== null ? [{ label: "Գումար", value: formatMoney(asking.row.amount) }] : []),
          ],
          confirmLabel: "Ջնջել",
          tone: "danger",
        };

  return (
    <div className="space-y-4">
      {confirm ? (
        <ConfirmDialog
          open
          content={confirm}
          pending={pending}
          error={error}
          onConfirm={answer}
          onCancel={() => {
            setAsking(null);
            setError(null);
          }}
        />
      ) : null}

      {bulk.length > 0 ? (
        <Card className="space-y-2">
          <p className="text-sm text-ink">
            Ամբողջ աղյուսակ է կրկնվել։ Կարող եք միանգամից ջնջել նրա կրկնօրինակ տողերը. բնօրինակները կմնան։
          </p>
          <div className="flex flex-wrap gap-2">
            {bulk.map((t) => (
              <Button
                key={t.tableId}
                size="sm"
                variant="secondary"
                onClick={() => setAsking({ kind: "copies", table: t.table, ids: t.ids.slice(0, 500), groups: t.groups })}
              >
                <Trash2 className="h-4 w-4" />
                «{t.table}»՝ {t.ids.length} կրկնօրինակ
              </Button>
            ))}
          </div>
        </Card>
      ) : null}

      {shown.map((group) => {
        const labels = labelsOf(group);
        const counted = group.rows.filter((r) => r.copyOf === null).length;
        return (
          <Card key={group.key} className="space-y-3">
            <div className="flex flex-wrap items-center gap-2">
              <Copy className="h-4 w-4 text-ink-muted" aria-hidden />
              <span className="font-medium text-ink">
                {SECTIONS[group.section].label} · {formatMoment(group.day)}
              </span>
              {group.kind === "exact" ? (
                <Badge tone="info">Նույն գրառումն է · հաշվվում է մեկ անգամ</Badge>
              ) : (
                <Badge tone="warning">Գուցե նույնն է · հաշվվում է {counted} անգամ</Badge>
              )}
              <Link
                href={`/admin/calendar?view=month&day=${group.day}&open=1`}
                className="ml-auto inline-flex items-center gap-1 text-xs text-ink-muted hover:text-primary"
              >
                <CalendarDays className="h-3.5 w-3.5" />
                Օրացույցում
              </Link>
            </div>

            <div className="-mx-1 overflow-x-auto px-1">
              <table className="w-full min-w-[28rem] border-separate border-spacing-0 text-sm">
                <thead>
                  <tr>
                    <th className="w-24 py-1 pr-3 text-left text-xs font-medium text-ink-muted sm:w-40" />
                    {group.rows.map((row) => (
                      <th key={row.id} className="py-1 pr-3 text-left align-bottom">
                        <Link href={tablePath(group.section, row.tableId)} className="font-medium text-ink hover:text-primary">
                          {row.table}
                        </Link>
                        <span className="block text-xs font-normal text-ink-faint">
                          {row.source === "google" ? "Google օրացույցից" : row.copyOf ? "Կրկնօրինակ · չի հաշվվում" : "Հաշվվում է"}
                        </span>
                      </th>
                    ))}
                  </tr>
                </thead>
                <tbody>
                  <tr>
                    <td className="border-t border-border py-1.5 pr-3 text-xs text-ink-muted">Ժամ</td>
                    {group.rows.map((row) => (
                      <td key={row.id} className={cn("border-t border-border py-1.5 pr-3", !row.time && group.rows.some((r) => r.time) && "text-warning")}>
                        {row.time ?? "—"}
                      </td>
                    ))}
                  </tr>
                  {labels.map((label) => (
                    <tr key={label}>
                      <td className="border-t border-border py-1.5 pr-3 text-xs text-ink-muted">{label}</td>
                      {group.rows.map((row) => (
                        <td
                          key={row.id}
                          className={cn("border-t border-border py-1.5 pr-3", row.missing.includes(label) && "bg-warning-light text-warning")}
                        >
                          {row.missing.includes(label) ? "չկա" : text(row.values[label])}
                        </td>
                      ))}
                    </tr>
                  ))}
                  <tr>
                    <td className="border-t border-border py-2 pr-3" />
                    {group.rows.map((row) => (
                      <td key={row.id} className="border-t border-border py-2 pr-3">
                        <Button
                          size="sm"
                          variant="ghost"
                          className="text-danger"
                          onClick={() => setAsking({ kind: "delete", group, row })}
                        >
                          <Trash2 className="h-4 w-4" />
                          Ջնջել այս մեկը
                        </Button>
                      </td>
                    ))}
                  </tr>
                </tbody>
              </table>
            </div>

            <div className="flex flex-wrap items-center gap-2">
              <Button size="sm" variant="secondary" onClick={() => setAsking({ kind: "keep", group })}>
                Թողնել {group.rows.length === 2 ? "երկուսն էլ" : "բոլորը"}՝ տարբեր գրառումներ են
              </Button>
              {group.rows.some((r) => r.missing.length > 0) ? (
                <span className="text-xs text-ink-muted">Դեղինով՝ դաշտեր, որոնք կան մի տողում և չկան մյուսում։</span>
              ) : null}
            </div>
          </Card>
        );
      })}
    </div>
  );
}

/** «N groups of rows may be one record» with a link to look at them. */
export function DuplicatesNotice({ count, className }: { count: number; className?: string }) {
  if (count <= 0) return null;
  return (
    <Link
      href="/admin/duplicates"
      className={cn(
        "flex items-center gap-2 rounded-md bg-warning-light px-3 py-2 text-sm text-warning hover:underline",
        className,
      )}
    >
      <Copy className="h-4 w-4 shrink-0" />
      <span>
        Գտնվել է {count} կրկնվող գրառում տարբեր աղյուսակներում․ ստուգեք՝ թողնել բոլորը, թե ջնջել ավելորդը։
      </span>
    </Link>
  );
}
