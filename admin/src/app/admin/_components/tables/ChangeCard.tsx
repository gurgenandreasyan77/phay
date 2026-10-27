"use client";

// A change of the business's tables an AI agent prepared in the chat (the
// API's TableChanges): a new table, its columns, rows added, edited or
// deleted, tables deleted. The card lists exactly what changes, value by
// value; nothing is written until the owner presses «Կիրառել». Once
// applied it links to the table and can be undone while nobody touched
// what it changed (deleting tables can't be undone).
import { useEffect, useState } from "react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { Pencil, Rows3, TableProperties, Trash2, Undo2 } from "lucide-react";
import type { DataCellValue, TableChangeDto } from "@/lib/adminApi";
import { friendlyApiError, readApiError } from "@/lib/apiError";
import { Badge, Button, cn, type BadgeTone } from "@/ui";
import { ConfirmDialog, type ConfirmContent } from "@/ui/dialog/ConfirmDialog";
import { formatCell, formatNumber, tablePath } from "./shared";

async function failure(response: Response | null): Promise<string> {
  if (!response) return "Սերվերի հետ կապ չկա։ Փորձեք նորից։";
  const error = await readApiError(response);
  return error.code === "change_refused" ? error.message : friendlyApiError(error);
}

const STATUS: Record<TableChangeDto["status"], { label: string; tone: BadgeTone }> = {
  planned: { label: "Սպասում է հաստատման", tone: "pending" },
  applied: { label: "Կիրառված է", tone: "success" },
  undone: { label: "Հետ է բերվել", tone: "default" },
  discarded: { label: "Չեղարկված է", tone: "default" },
};

const DELETES: TableChangeDto["kind"][] = ["delete_rows", "delete_tables"];

function KindIcon({ kind }: { kind: TableChangeDto["kind"] }) {
  const Icon =
    kind === "create_table"
      ? TableProperties
      : kind === "edit_table" || kind === "update_rows"
        ? Pencil
        : DELETES.includes(kind)
          ? Trash2
          : Rows3;
  return (
    <span
      className={cn(
        "flex h-9 w-9 shrink-0 items-center justify-center rounded-lg",
        DELETES.includes(kind) ? "bg-danger-light text-danger" : "bg-primary-light text-primary",
      )}
    >
      <Icon className="h-[18px] w-[18px]" strokeWidth={1.8} />
    </span>
  );
}

/** The rows the change adds, edits (old value struck through) or deletes. */
function ChangeRows({ change }: { change: TableChangeDto }) {
  if (change.rows.length === 0 || change.columns.length === 0) return null;
  const deleting = change.kind === "delete_rows";
  const adding = change.kind === "add_rows" || change.kind === "create_table";
  const cell = (value: DataCellValue | undefined, type: TableChangeDto["columns"][number]["type"]) => formatCell(value, { type });

  return (
    <div className="space-y-1">
      <div className="-mx-1 overflow-x-auto px-1">
        <table className="w-full min-w-max border-separate border-spacing-0 text-left text-sm">
          <thead>
            <tr>
              {change.kind !== "create_table" && !adding ? (
                <th className="border-b border-border px-2 py-1.5 text-xs font-medium text-ink-faint">#</th>
              ) : null}
              {change.columns.map((c) => (
                <th
                  key={c.key}
                  className="border-b border-border px-2 py-1.5 text-xs font-medium whitespace-nowrap text-ink-muted"
                >
                  {c.label}
                </th>
              ))}
            </tr>
          </thead>
          <tbody>
            {change.rows.map((row, i) => (
              <tr
                key={row.id ?? `n${i}`}
                className={cn(deleting && "bg-danger-light/40 text-ink-muted line-through", adding && "bg-success-light/40")}
              >
                {change.kind !== "create_table" && !adding ? (
                  <td className="border-b border-border/60 px-2 py-1.5 text-xs text-ink-faint">{row.id}</td>
                ) : null}
                {change.columns.map((c) => {
                  const changed = row.changed?.includes(c.key);
                  return (
                    <td
                      key={c.key}
                      className={cn(
                        "max-w-[16rem] border-b border-border/60 px-2 py-1.5 align-top whitespace-nowrap",
                        changed && "bg-warning-light/60",
                      )}
                    >
                      {changed ? (
                        <span className="flex flex-col">
                          <span className="text-xs text-ink-faint line-through">{cell(row.before?.[c.key], c.type)}</span>
                          <span className="font-medium text-ink">{cell(row.values[c.key], c.type)}</span>
                        </span>
                      ) : (
                        <span className="block truncate">{cell(row.values[c.key], c.type)}</span>
                      )}
                    </td>
                  );
                })}
              </tr>
            ))}
          </tbody>
        </table>
      </div>
      {change.rowsTotal > change.rows.length ? (
        <p className="text-xs text-ink-muted">և ևս {formatNumber(change.rowsTotal - change.rows.length)} տող</p>
      ) : null}
    </div>
  );
}

function confirmContent(change: TableChangeDto): ConfirmContent {
  const danger = DELETES.includes(change.kind);
  return {
    title: `${change.title}?`,
    description:
      change.kind === "delete_tables"
        ? "Աղյուսակները կջնջվեն իրենց բոլոր տողերով։ Սա հետ բերել հնարավոր չէ։"
        : "Փոփոխությունը կկատարվի ճիշտ այնպես, ինչպես ցույց է տրված։ Հետո կարող եք հետ բերել, քանի դեռ այն ոչ ոք չի փոխել։",
    details: change.rowsTotal > 0 ? [{ label: "Տողեր", value: formatNumber(change.rowsTotal) }] : undefined,
    confirmLabel: danger ? "Ջնջել" : "Կիրառել",
    tone: danger ? "danger" : "primary",
  };
}

export function ChangeCard({ id }: { id: number }) {
  const router = useRouter();
  const [change, setChange] = useState<TableChangeDto | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [asking, setAsking] = useState<"apply" | "undo" | null>(null);
  const [pending, setPending] = useState(false);

  useEffect(() => {
    let cancelled = false;
    fetch(`/api/admin/table-changes/${id}`)
      .catch(() => null)
      .then(async (response) => {
        if (cancelled) return;
        if (response?.ok) setChange(((await response.json()) as { data: TableChangeDto }).data);
        else setError(await failure(response));
      });
    return () => {
      cancelled = true;
    };
  }, [id]);

  async function act(action: "apply" | "undo" | "discard") {
    setPending(true);
    setError(null);
    const response = await fetch(`/api/admin/table-changes/${id}/${action}`, { method: "POST" }).catch(() => null);
    setPending(false);
    setAsking(null);
    if (!response?.ok) {
      setError(await failure(response));
      return;
    }
    setChange(((await response.json()) as { data: TableChangeDto }).data);
    router.refresh();
  }

  const status = change ? STATUS[change.status] : null;
  const planned = change?.status === "planned" && !change.stale;
  const danger = change ? DELETES.includes(change.kind) : false;
  const tableLink = change?.table && !change.table.archived && change.status !== "discarded" && change.kind !== "delete_tables";

  return (
    <div className="mt-2 space-y-3 rounded-xl border border-border bg-surface p-3 sm:p-4">
      <div className="flex items-start gap-3">
        {change ? <KindIcon kind={change.kind} /> : null}
        <div className="min-w-0 flex-1">
          <p className="text-sm font-semibold break-words text-ink">{change?.title ?? "Աղյուսակի փոփոխություն"}</p>
          {status ? (
            <Badge tone={status.tone} className="mt-1">
              {status.label}
            </Badge>
          ) : null}
        </div>
      </div>

      {!change && !error ? <p className="text-sm text-ink-muted">Բեռնվում է…</p> : null}

      {change && change.changes.length > 0 ? (
        <ul className="space-y-1 text-sm text-ink">
          {change.changes.map((line, i) => (
            <li key={i} className="flex gap-2">
              <span className={cn("mt-2 h-1.5 w-1.5 shrink-0 rounded-full", danger ? "bg-danger" : "bg-primary")} />
              <span className="min-w-0 break-words">{line}</span>
            </li>
          ))}
        </ul>
      ) : null}

      {change ? <ChangeRows change={change} /> : null}

      {change?.stale && change.status === "planned" ? <p className="text-sm text-warning">{change.stale}</p> : null}
      {error ? <p className="text-sm whitespace-pre-line text-danger">{error}</p> : null}

      {change && planned ? (
        <div className="flex flex-wrap items-center gap-2">
          <Button size="sm" variant={danger ? "danger" : "primary"} onClick={() => setAsking("apply")}>
            {danger ? <Trash2 className="h-4 w-4" /> : null}
            {danger ? "Ջնջել" : "Կիրառել"}
          </Button>
          <Button size="sm" variant="ghost" disabled={pending} onClick={() => act("discard")}>
            Չեղարկել
          </Button>
          <span className="text-xs text-ink-muted">Մինչև սեղմելը ոչինչ չի փոխվում։</span>
        </div>
      ) : null}

      {change?.status === "applied" ? (
        <div className="flex flex-wrap items-center gap-2">
          {tableLink && change.table ? (
            <Link href={tablePath(change.section, change.table.id)} className="text-sm font-medium text-primary hover:underline">
              Բացել «{change.table.name}»
            </Link>
          ) : null}
          {change.undoable && !change.undoBlocked ? (
            <Button size="sm" variant="secondary" onClick={() => setAsking("undo")}>
              <Undo2 className="h-4 w-4" />
              Հետ բերել
            </Button>
          ) : null}
          {change.undoable && change.undoBlocked ? (
            <span className="text-xs text-ink-muted">Հետ բերել այլևս հնարավոր չէ․ {change.undoBlocked}</span>
          ) : null}
        </div>
      ) : null}

      {change ? (
        <ConfirmDialog
          open={asking !== null}
          pending={pending}
          onCancel={() => setAsking(null)}
          onConfirm={() => act(asking ?? "apply")}
          content={
            asking === "undo"
              ? {
                  title: "Հետ բերե՞լ փոփոխությունը",
                  description: "Ամեն ինչ կվերադառնա այնպես, ինչպես էր մինչև այս փոփոխությունը։",
                  confirmLabel: "Հետ բերել",
                }
              : confirmContent(change)
          }
        />
      ) : null}
    </div>
  );
}
