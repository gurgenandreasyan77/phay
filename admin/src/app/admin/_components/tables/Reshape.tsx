"use client";

// Splitting tables into periods and putting them together (the API's
// TableReshaper): a year's table into the months that have rows, months
// into a quarter, several tables into one. A preview first; the chosen
// tables are archived, not deleted, and it can be undone while the new
// tables are untouched. The same cards show a reshape an AI agent planned
// in the chat, for the owner to apply.
import { useEffect, useId, useState } from "react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { CalendarRange, Info, Undo2, X } from "lucide-react";
import type {
  AdminDataTableDto,
  DataTableSection,
  ReshapeUndated,
  TablePeriod,
  TableReshapeDto,
  TableReshapePreviewDto,
} from "@/lib/adminApi";
import { friendlyApiError, readApiError } from "@/lib/apiError";
import { Button, cn } from "@/ui";
import { Dialog, DialogClose } from "@/ui/dialog/Dialog";
import { ConfirmDialog } from "@/ui/dialog/ConfirmDialog";
import { formatMoment, formatMoney, formatNumber, tablePath, tablesPath } from "./shared";
import { PERIOD_KINDS, TABLE_PERIOD_LABELS } from "./periods";

const TARGETS: TablePeriod[] = [...PERIOD_KINDS, "custom"];

/** «Ամսական», but «Մեկ աղյուսակ» for all rows in one table. */
const TARGET_LABELS: Record<TablePeriod, string> = { ...TABLE_PERIOD_LABELS, custom: "Մեկ աղյուսակ" };

const ORDER: TablePeriod[] = ["day", "week", "month", "quarter", "year", "custom"];

/** What the chosen tables most likely become: one table one step smaller, several one step bigger. */
function guess(tables: AdminDataTableDto[]): TablePeriod {
  const kinds = tables.map((t) => t.periodInfo?.kind ?? "custom");
  if (tables.length === 1) {
    const kind = kinds[0];
    return kind === "custom" || kind === "year" ? "month" : kind === "quarter" ? "month" : kind === "month" ? "week" : "day";
  }
  const biggest = Math.max(...kinds.map((k) => ORDER.indexOf(k)));
  return biggest >= ORDER.indexOf("year") ? "custom" : ORDER[biggest + 1];
}

async function failure(response: Response | null): Promise<string> {
  if (!response) return "Սերվերի հետ կապ չկա։ Փորձեք նորից։";
  const error = await readApiError(response);
  return error.code === "reshape_refused" ? error.message : friendlyApiError(error);
}

function dates(first: string | null, last: string | null): string {
  if (!first || !last) return "";
  const a = formatMoment(first.slice(0, 10));
  const b = formatMoment(last.slice(0, 10));
  return a === b ? a : `${a} – ${b}`;
}

/** The new tables of a preview, with their rows, money and dates. */
export function ReshapeTargets({
  preview,
  max = 60,
}: {
  preview: Pick<TableReshapePreviewDto, "targets" | "rows" | "undatedRows">;
  max?: number;
}) {
  const shown = preview.targets.slice(0, max);

  return (
    <div className="space-y-2">
      <p className="text-sm font-medium text-ink">
        Կստեղծվի {preview.targets.length} աղյուսակ · {formatNumber(preview.rows)} տող
      </p>
      <ul className="overscroll-contain max-h-64 divide-y divide-border overflow-y-auto rounded-lg border border-border">
        {shown.map((t) => (
          <li key={t.key} className="flex flex-wrap items-baseline justify-between gap-x-3 gap-y-0.5 px-3 py-2 text-sm">
            <span className={cn("min-w-0 font-medium", t.undated ? "text-warning" : "text-ink")}>{t.name}</span>
            <span className="whitespace-nowrap text-xs text-ink-muted">
              {t.rows} տող{t.amount ? ` · ${formatMoney(t.amount)}` : ""}
              {!t.undated && t.firstAt ? ` · ${dates(t.firstAt, t.lastAt)}` : ""}
            </span>
          </li>
        ))}
        {preview.targets.length > shown.length ? (
          <li className="px-3 py-2 text-xs text-ink-muted">և ևս {preview.targets.length - shown.length} աղյուսակ</li>
        ) : null}
      </ul>
    </div>
  );
}

/**
 * Choose the period of the new tables, see what they'll be and apply. The
 * tables chosen in the list come in; another table of the same period can
 * be added from the warning.
 */
export function ReshapeDialog({
  open,
  onClose,
  section,
  tables,
  initialIds,
}: {
  open: boolean;
  onClose: () => void;
  section: DataTableSection;
  /** Every table of the section, to name the chosen ones and add more. */
  tables: AdminDataTableDto[];
  initialIds: number[];
}) {
  const router = useRouter();
  const titleId = useId();
  const [ids, setIds] = useState<number[]>(initialIds);
  const [period, setPeriod] = useState<TablePeriod>(() => guess(tables.filter((t) => initialIds.includes(t.id))));
  const [undated, setUndated] = useState<ReshapeUndated>("separate");
  const [preview, setPreview] = useState<TableReshapePreviewDto | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(false);
  const [pending, setPending] = useState(false);

  useEffect(() => {
    if (!open || ids.length === 0) return;
    let cancelled = false;
    // eslint-disable-next-line react-hooks/set-state-in-effect -- the preview follows the choice
    setLoading(true);
    fetch("/api/admin/table-reshapes/preview", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ ids, period, undated }),
    })
      .catch(() => null)
      .then(async (response) => {
        if (cancelled) return;
        if (response?.ok) {
          setPreview(((await response.json()) as { data: TableReshapePreviewDto }).data);
          setError(null);
        } else {
          setPreview(null);
          setError(await failure(response));
        }
        setLoading(false);
      });
    return () => {
      cancelled = true;
    };
  }, [open, ids, period, undated]);

  async function apply() {
    setPending(true);
    const response = await fetch("/api/admin/table-reshapes", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ ids, period, undated }),
    }).catch(() => null);
    if (!response?.ok) {
      setPending(false);
      setError(await failure(response));
      return;
    }
    const reshape = ((await response.json()) as { data: TableReshapeDto }).data;
    setPending(false);
    onClose();
    router.push(`${tablesPath(section)}?reshape=${reshape.id}`);
    router.refresh();
  }

  const chosen = tables.filter((t) => ids.includes(t.id));

  return (
    <Dialog
      open={open}
      onClose={() => (pending ? undefined : onClose())}
      labelledBy={titleId}
      className="w-[min(40rem,calc(100vw-2rem))]"
    >
      <div className="space-y-4">
        <div className="flex items-start gap-3">
          <span className="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-primary-light text-primary">
            <CalendarRange className="h-5 w-5" strokeWidth={1.9} />
          </span>
          <div className="min-w-0 flex-1 space-y-1 pt-0.5">
            <h2 id={titleId} className="text-base font-semibold text-ink">
              Բաժանել կամ միավորել
            </h2>
            <p className="text-sm text-ink-muted">
              Տողերը կբաշխվեն ըստ իրենց ամսաթվի․ աղյուսակ կստեղծվի միայն այն ժամանակահատվածների համար, որտեղ տվյալներ կան։
            </p>
          </div>
          <DialogClose onClick={onClose} />
        </div>

        <div className="space-y-1.5">
          <p className="text-xs font-medium text-ink-muted">Ընտրված աղյուսակներ ({chosen.length})</p>
          <ul className="flex flex-wrap gap-1.5">
            {chosen.map((t) => (
              <li
                key={t.id}
                className="flex items-center gap-1 rounded-full bg-surface-secondary py-1 pl-3 pr-1 text-sm text-ink"
              >
                <span className="max-w-56 truncate">{t.name}</span>
                {chosen.length > 1 ? (
                  <button
                    type="button"
                    className="flex h-6 w-6 items-center justify-center rounded-full text-ink-muted hover:bg-surface hover:text-ink"
                    aria-label={`Հանել «${t.name}»`}
                    onClick={() => setIds((current) => current.filter((id) => id !== t.id))}
                  >
                    <X className="h-3.5 w-3.5" />
                  </button>
                ) : (
                  <span className="w-2" />
                )}
              </li>
            ))}
          </ul>
        </div>

        <div className="space-y-1.5">
          <p className="text-xs font-medium text-ink-muted">Նոր աղյուսակները</p>
          <div
            className="segmented flex flex-wrap gap-1 rounded-md border border-border bg-surface p-1"
            role="radiogroup"
            aria-label="Նոր աղյուսակների ժամանակահատվածը"
          >
            {TARGETS.map((kind) => (
              <button
                key={kind}
                type="button"
                role="radio"
                aria-checked={period === kind}
                onClick={() => setPeriod(kind)}
                className={cn(
                  "rounded px-3 py-1.5 text-sm font-medium",
                  period === kind ? "bg-primary text-primary-foreground" : "text-ink-muted hover:text-ink",
                )}
              >
                {TARGET_LABELS[kind]}
              </button>
            ))}
          </div>
        </div>

        <div className={cn("space-y-3 transition-opacity", loading && "opacity-60")} aria-live="polite">
          {error ? <p className="rounded-md bg-danger-light px-3 py-2 text-sm text-danger">{error}</p> : null}
          {preview && !error ? (
            <>
              <ReshapeTargets preview={preview} />

              {preview.undatedRows > 0 ? (
                <fieldset className="space-y-1.5 rounded-lg border border-border px-3 py-2">
                  <legend className="px-1 text-xs font-medium text-ink-muted">Առանց ամսաթվի տողեր՝ {preview.undatedRows}</legend>
                  {(
                    [
                      ["separate", "Առանձին աղյուսակում"],
                      ["latest", "Ամենավերջին աղյուսակում"],
                    ] as const
                  ).map(([value, label]) => (
                    <label key={value} className="flex items-center gap-2 text-sm text-ink">
                      <input type="radio" name="undated" checked={undated === value} onChange={() => setUndated(value)} />
                      {label}
                    </label>
                  ))}
                </fieldset>
              ) : null}

              {preview.addedColumns.length > 0 ? (
                <p className="text-sm text-ink-muted">
                  Սյունակները կմիավորվեն․ կավելանան՝ {preview.addedColumns.map((c) => `«${c}»`).join(", ")}։
                </p>
              ) : null}

              {preview.conflicts.map((c) => (
                <div
                  key={c.id}
                  className="flex flex-wrap items-center justify-between gap-2 rounded-md bg-warning-light px-3 py-2 text-sm text-warning"
                >
                  <span>
                    «{c.name}» աղյուսակն արդեն կա {c.period ?? "այդ ժամանակահատվածի"} համար։ Միավորե՞լ այն էլ։
                  </span>
                  <Button size="sm" variant="secondary" onClick={() => setIds((current) => [...current, c.id])}>
                    Ավելացնել
                  </Button>
                </div>
              ))}
            </>
          ) : null}

          <p className="flex gap-2 text-xs text-ink-muted">
            <Info className="mt-0.5 h-3.5 w-3.5 shrink-0" />
            Ընտրված աղյուսակները չեն ջնջվի․ դրանք կմնան ցուցակում որպես հին տարբերակ և չեն հաշվվի վերլուծության մեջ։ Կարող եք հետ
            բերել, քանի դեռ նոր աղյուսակները չեք փոխել։
          </p>
        </div>

        <div className="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
          <Button variant="secondary" onClick={onClose} disabled={pending}>
            Չեղարկել
          </Button>
          <Button onClick={apply} disabled={pending || loading || !preview || !!error}>
            {pending ? "Մի պահ…" : preview ? `Ստեղծել ${preview.targets.length} աղյուսակ` : "Կիրառել"}
          </Button>
        </div>
      </div>
    </Dialog>
  );
}

/** Undo an applied reshape, or say why it can't be undone any more. */
function UndoButton({ reshape, onDone }: { reshape: TableReshapeDto; onDone: (r: TableReshapeDto) => void }) {
  const [confirming, setConfirming] = useState(false);
  const [pending, setPending] = useState(false);
  const [error, setError] = useState<string | null>(null);

  if (reshape.undoBlocked) {
    return <p className="text-xs text-ink-muted">Հետ բերել այլևս հնարավոր չէ․ {reshape.undoBlocked}</p>;
  }

  async function undo() {
    setPending(true);
    const response = await fetch(`/api/admin/table-reshapes/${reshape.id}/undo`, { method: "POST" }).catch(() => null);
    setPending(false);
    if (!response?.ok) {
      setError(await failure(response));
      return;
    }
    setConfirming(false);
    onDone(((await response.json()) as { data: TableReshapeDto }).data);
  }

  return (
    <>
      <Button size="sm" variant="secondary" onClick={() => setConfirming(true)}>
        <Undo2 className="h-4 w-4" />
        Հետ բերել
      </Button>
      <ConfirmDialog
        open={confirming}
        pending={pending}
        error={error}
        onCancel={() => setConfirming(false)}
        onConfirm={undo}
        content={{
          title: "Հետ բերե՞լ նախկին աղյուսակները",
          description: `Նոր ${reshape.results?.length ?? 0} աղյուսակը կհեռացվի, իսկ նախկինները կվերադառնան և նորից կհաշվվեն վերլուծության մեջ։`,
          confirmLabel: "Հետ բերել",
        }}
      />
    </>
  );
}

/** What an applied (or undone) reshape did, with links to the new tables. */
function ReshapeDone({ reshape, onChange }: { reshape: TableReshapeDto; onChange: (r: TableReshapeDto) => void }) {
  const section = reshape.section;

  if (reshape.status === "undone") {
    return <p className="text-sm text-ink-muted">Փոփոխությունը հետ է բերվել․ նախկին աղյուսակները վերադարձվել են։</p>;
  }

  const results = reshape.results ?? [];
  return (
    <div className="space-y-2">
      <p className="text-sm text-ink">
        Ստեղծվեց {results.length} աղյուսակ ({TABLE_PERIOD_LABELS[reshape.period].toLowerCase()})․ {reshape.sources?.length ?? 0}{" "}
        նախկին աղյուսակը պահվեց որպես հին տարբերակ։
      </p>
      <ul className="flex flex-wrap gap-1.5">
        {results.slice(0, 24).map((t) => (
          <li key={t.id}>
            <Link
              href={tablePath(section, t.id)}
              className="inline-block rounded-full bg-surface-secondary px-3 py-1 text-sm text-ink hover:text-primary"
            >
              {t.name} <span className="text-ink-faint">· {t.rows}</span>
            </Link>
          </li>
        ))}
        {results.length > 24 ? <li className="px-1 py-1 text-sm text-ink-muted">և ևս {results.length - 24}</li> : null}
      </ul>
      <UndoButton reshape={reshape} onDone={onChange} />
    </div>
  );
}

/** On the tables list after a reshape (?reshape=): what was made, and undo. */
export function ReshapeBanner({ reshape: initial }: { reshape: TableReshapeDto }) {
  const router = useRouter();
  const [reshape, setReshape] = useState(initial);

  return (
    <div className="rounded-xl border border-primary/40 bg-primary-light/40 p-4">
      <ReshapeDone
        reshape={reshape}
        onChange={(next) => {
          setReshape(next);
          router.refresh();
        }}
      />
    </div>
  );
}

/**
 * A reshape an AI agent planned, under its answer: the preview as the
 * tables are now and «Apply»; once applied, what it made and undo.
 */
export function ReshapeCard({ id }: { id: number }) {
  const router = useRouter();
  const [reshape, setReshape] = useState<TableReshapeDto | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [confirming, setConfirming] = useState(false);
  const [pending, setPending] = useState(false);

  useEffect(() => {
    let cancelled = false;
    fetch(`/api/admin/table-reshapes/${id}`)
      .catch(() => null)
      .then(async (response) => {
        if (cancelled) return;
        if (response?.ok) setReshape(((await response.json()) as { data: TableReshapeDto }).data);
        else setError(await failure(response));
      });
    return () => {
      cancelled = true;
    };
  }, [id]);

  async function apply() {
    setPending(true);
    const response = await fetch(`/api/admin/table-reshapes/${id}/apply`, { method: "POST" }).catch(() => null);
    setPending(false);
    if (!response?.ok) {
      setError(await failure(response));
      setConfirming(false);
      return;
    }
    setConfirming(false);
    setReshape(((await response.json()) as { data: TableReshapeDto }).data);
    router.refresh();
  }

  const planned = reshape?.status === "planned" && !reshape.stale && reshape.targets;

  return (
    <div className="mt-2 space-y-3 rounded-xl border border-border bg-surface p-3 sm:p-4">
      <p className="flex items-center gap-2 text-sm font-semibold text-ink">
        <CalendarRange className="h-4 w-4 text-primary" />
        {reshape ? `Աղյուսակներ՝ ${TARGET_LABELS[reshape.period].toLowerCase()}` : "Աղյուսակների բաժանում"}
      </p>
      {!reshape && !error ? <p className="text-sm text-ink-muted">Բեռնվում է…</p> : null}
      {error ? <p className="text-sm text-danger">{error}</p> : null}
      {reshape?.stale ? <p className="text-sm text-warning">{reshape.stale}</p> : null}
      {planned && reshape.targets ? (
        <>
          <p className="text-sm text-ink-muted">Ընտրված՝ {(reshape.sources ?? []).map((s) => `«${s.name}»`).join(", ")}</p>
          <ReshapeTargets
            preview={{ targets: reshape.targets, rows: reshape.rows ?? 0, undatedRows: reshape.undatedRows ?? 0 }}
            max={12}
          />
          {(reshape.undatedRows ?? 0) > 0 ? (
            <p className="text-xs text-ink-muted">
              Առանց ամսաթվի {reshape.undatedRows} տող՝{" "}
              {reshape.undated === "latest" ? "ամենավերջին աղյուսակում" : "առանձին աղյուսակում"}։
            </p>
          ) : null}
          <div className="flex flex-wrap items-center gap-2">
            <Button size="sm" onClick={() => setConfirming(true)}>
              Կիրառել
            </Button>
            <span className="text-xs text-ink-muted">Մինչև սեղմելը ոչինչ չի փոխվում։</span>
          </div>
          <ConfirmDialog
            open={confirming}
            pending={pending}
            onCancel={() => setConfirming(false)}
            onConfirm={apply}
            content={{
              title: `Ստեղծե՞լ ${reshape.targets.length} աղյուսակ`,
              description:
                "Տողերը կտեղափոխվեն նոր աղյուսակներ, իսկ ընտրվածները կպահվեն որպես հին տարբերակ։ Կարող եք հետ բերել, քանի դեռ նոր աղյուսակները չեք փոխել։",
              details: [
                { label: "Տողեր", value: formatNumber(reshape.rows ?? 0) },
                { label: "Նոր աղյուսակներ", value: String(reshape.targets.length) },
              ],
              confirmLabel: "Կիրառել",
            }}
          />
        </>
      ) : null}
      {reshape && reshape.status !== "planned" ? <ReshapeDone reshape={reshape} onChange={setReshape} /> : null}
    </div>
  );
}
