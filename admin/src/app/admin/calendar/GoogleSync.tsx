"use client";

import Link from "next/link";
import { useRouter } from "next/navigation";
import { useEffect, useId, useState } from "react";
import { CalendarCog, Check, RefreshCw, Table2, Unplug } from "lucide-react";
import type { GoogleStateDto, GoogleTablePreviewDto } from "@/lib/adminApi";
import { Badge, Button, buttonClasses, Card, cn } from "@/ui";
import { ConfirmDialog, type ConfirmContent } from "@/ui/dialog/ConfirmDialog";
import { Dialog, DialogClose } from "@/ui/dialog/Dialog";
import { formatDateTime, tablePath } from "../_components/tables/shared";

/** What came back from Google's consent page (?google=…). */
const NOTICES: Record<string, { text: string; tone: "success" | "danger" }> = {
  connected: { text: "Google հաշիվը միացված է։ Ընտրեք, թե որ օրացույցներից բերել պատվերները։", tone: "success" },
  denied: { text: "Google-ում թույլտվությունը չտրվեց, հաշիվը չի միացվել։", tone: "danger" },
  expired: { text: "Միացման հղումը ժամկետանց է։ Փորձեք նորից։", tone: "danger" },
  not_configured: { text: "Google օրացույցի հետ կապը հարթակում դեռ կարգավորված չէ։", tone: "danger" },
  error: { text: "Չհաջողվեց կապվել Google-ի հետ։ Փորձեք մի փոքր ուշ։", tone: "danger" },
};

type Asking = "sync" | "table" | "disconnect" | null;

/** Google's «G», in its colours: the mark of everything that came from Google. */
function GoogleMark({ className }: { className?: string }) {
  return (
    <svg viewBox="0 0 48 48" aria-hidden="true" className={className}>
      <path fill="#EA4335" d="M24 9.5c3.5 0 6.6 1.2 9.1 3.6l6.8-6.8C35.8 2.4 30.3 0 24 0 14.6 0 6.6 5.4 2.7 13.2l7.9 6.1C12.5 13.5 17.8 9.5 24 9.5z" />
      <path fill="#4285F4" d="M46.1 24.5c0-1.6-.1-3.1-.4-4.5H24v9h12.4c-.5 2.9-2.2 5.3-4.6 6.9l7.4 5.7c4.3-4 6.9-9.9 6.9-17.1z" />
      <path fill="#FBBC05" d="M10.6 28.7c-.5-1.4-.8-3-.8-4.7s.3-3.2.8-4.7l-7.9-6.1C1 16.6 0 20.2 0 24s1 7.4 2.7 10.8l7.9-6.1z" />
      <path fill="#34A853" d="M24 48c6.5 0 11.9-2.1 15.9-5.8l-7.4-5.7c-2.1 1.4-4.8 2.3-8.5 2.3-6.2 0-11.5-4-13.4-9.6l-7.9 6.1C6.6 42.6 14.6 48 24 48z" />
    </svg>
  );
}

function changes(p: GoogleTablePreviewDto): number {
  return p.new + p.changed + p.removed;
}

/**
 * Google Calendar on the calendar page: connecting the person's account
 * (read only), choosing its calendars, syncing them, and turning the events
 * into an order table. A table already made is never overwritten: when the
 * events changed, a new version is made and the old one is kept.
 */
export function GoogleSync({
  state,
  canMakeTable,
  staff,
  notice,
}: {
  state: GoogleStateDto;
  canMakeTable: boolean;
  staff: boolean;
  notice: string | null;
}) {
  const router = useRouter();
  const [asking, setAsking] = useState<Asking>(null);
  const [preview, setPreview] = useState<GoogleTablePreviewDto | null>(null);
  const [pending, setPending] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [message, setMessage] = useState<{ text: string; tone: "success" | "danger"; table?: number } | null>(
    notice && NOTICES[notice] ? NOTICES[notice] : null,
  );
  const connection = state.connection;
  const selected = connection?.calendars.filter((c) => c.selected) ?? [];
  const [picking, setPicking] = useState(notice === "connected" && !!connection && !connection.needsReconnect);

  // The notice is shown once: a refresh doesn't bring it back.
  useEffect(() => {
    if (notice) router.replace("/admin/calendar", { scroll: false });
  }, [notice, router]);

  function failure(status: number, json: { code?: string } | null): string {
    if (json?.code === "google_reconnect") return "Google-ը այլևս թույլ չի տալիս կարդալ օրացույցը։ Միացրեք հաշիվը նորից։";
    if (status === 429) return "Շատ հաճախ եք սեղմում։ Փորձեք մեկ րոպե հետո։";
    if (status === 502) return "Google-ը չպատասխանեց։ Փորձեք մի փոքր ուշ։";
    return "Չհաջողվեց։ Փորձեք կրկին։";
  }

  async function sync() {
    setPending(true);
    setError(null);
    try {
      const response = await fetch("/api/admin/google/sync", { method: "POST" });
      const json = await response.json().catch(() => null);
      if (!response.ok) {
        const text = failure(response.status, json);
        setError(text);
        setMessage({ text, tone: "danger" });
        if (json?.code === "google_reconnect") router.refresh();
        return;
      }
      const { result } = json.data;
      setAsking(null);
      setMessage({
        text:
          result.added + result.updated + result.removed === 0
            ? "Համաժամեցված է․ Google-ում նոր փոփոխություններ չկան։"
            : `Համաժամեցված է․ ${result.added} նոր, ${result.updated} փոփոխված, ${result.removed} ջնջված իրադարձություն։`,
        tone: "success",
      });
      router.refresh();
    } catch {
      setError("Չհաջողվեց։ Ստուգեք կապը և փորձեք կրկին։");
    } finally {
      setPending(false);
    }
  }

  async function askTable() {
    setError(null);
    setPreview(state.pending);
    setAsking("table");
    // The figures as they are now, in case someone else made a table meanwhile.
    const response = await fetch("/api/admin/google/table").catch(() => null);
    const json = response?.ok ? await response.json().catch(() => null) : null;
    if (json?.data) setPreview(json.data);
  }

  async function makeTable() {
    const open = preview?.table ?? preview?.spread?.[0];
    if (preview?.upToDate && open) {
      router.push(tablePath("orders", open.id));
      return;
    }
    setPending(true);
    setError(null);
    try {
      const response = await fetch("/api/admin/google/table", { method: "POST" });
      const json = await response.json().catch(() => null);
      if (!response.ok) {
        setError(response.status === 422 ? "Դեռ համաժամեցված իրադարձություններ չկան։" : failure(response.status, json));
        return;
      }
      const { table, created, previous, spread, counts } = json.data;
      setAsking(null);
      setMessage({
        text: !created && spread?.length && !counts?.upToDate
          ? `Թարմացվեց․ ${counts.new} նոր, ${counts.changed} փոփոխված, ${counts.removed} ջնջված պատվեր՝ ըստ ամիսների աղյուսակներում։`
          : !created
          ? `«${table.name}» աղյուսակն արդեն թարմ է։`
          : previous
            ? `Ստեղծվեց նոր տարբերակը՝ «${table.name}»։ «${previous.name}»-ը պահված է որպես հին տարբերակ։`
            : `Ստեղծվեց «${table.name}» աղյուսակը՝ ${table.rowsCount} տողով։`,
        tone: "success",
        table: table.id,
      });
      router.refresh();
    } catch {
      setError("Չհաջողվեց։ Փորձեք կրկին։");
    } finally {
      setPending(false);
    }
  }

  async function disconnect() {
    setPending(true);
    setError(null);
    try {
      const response = await fetch("/api/admin/google", { method: "DELETE" });
      if (!response.ok) {
        setError("Չհաջողվեց անջատել։ Փորձեք կրկին։");
        return;
      }
      setAsking(null);
      setMessage({ text: "Google հաշիվն անջատված է։ Դրանից ստեղծված աղյուսակները մնացին։", tone: "success" });
      router.refresh();
    } catch {
      setError("Չհաջողվեց անջատել։ Փորձեք կրկին։");
    } finally {
      setPending(false);
    }
  }

  const confirm: ConfirmContent | null =
    asking === "sync" && connection
      ? {
          title: "Համաժամեցնե՞լ Google օրացույցը",
          description:
            "Կբերենք ընտրված օրացույցների նոր, փոփոխված և ջնջված իրադարձությունները։ Google-ում ոչինչ չի փոխվի. մենք միայն կարդում ենք։",
          details: [
            { label: "Հաշիվ", value: connection.email ?? "Google" },
            { label: "Օրացույցներ", value: selected.map((c) => c.name).join(", ") },
            { label: "Վերջին համաժամեցումը", value: connection.syncedAt ? formatDateTime(connection.syncedAt) : "Դեռ չի եղել" },
          ],
          confirmLabel: "Համաժամեցնել",
        }
      : asking === "table" && preview
        ? !preview.table && preview.spread?.length
          ? preview.upToDate
            ? {
                title: "Աղյուսակներն արդեն թարմ են",
                description: `Google-ի պատվերները ${preview.spread.map((t) => `«${t.name}»`).join(", ")} աղյուսակներում են, և բոլորը թարմ են։`,
                confirmLabel: "Բացել աղյուսակը",
                cancelLabel: "Փակել",
              }
            : {
                title: "Թարմացնե՞լ ամսական աղյուսակները",
                description: `Google-ի աղյուսակը բաժանված է՝ ${preview.spread.map((t) => `«${t.name}»`).join(", ")}։ Նոր պատվերները կավելանան իրենց ամսվա աղյուսակին (եթե չկա՝ կստեղծվի նույն սյունակներով), փոփոխվածները կթարմացվեն տեղում, Google-ում ջնջվածները կհեռանան։ Ձեռքով խմբագրված տողերը կմնան անփոփոխ։`,
                details: [
                  { label: "Նոր իրադարձություններ", value: String(preview.new) },
                  { label: "Փոփոխված", value: String(preview.changed) },
                  { label: "Ջնջված Google-ում", value: String(preview.removed) },
                  ...(preview.keptEdited > 0
                    ? [{ label: "Ձեռքով խմբագրված (պահպանվում են)", value: String(preview.keptEdited) }]
                    : []),
                ],
                confirmLabel: "Թարմացնել",
              }
          : !preview.table
          ? {
              title: "Ստեղծե՞լ աղյուսակ Google օրացույցից",
              description:
                "Կստեղծվի պատվերների նոր աղյուսակ։ Ամեն իրադարձություն կդառնա տող՝ ամսաթիվ և ժամ, անվանում, հասցե, իսկ նկարագրությունից՝ հաճախորդ, հեռախոս, գումար (օր.՝ «Հաճախորդ: Անի», «Գին: 9900»)։ Դրանից հետո պատվերները կհաշվվեն վերլուծության մեջ։",
              details: [{ label: "Իրադարձություններ", value: String(preview.events) }],
              confirmLabel: "Ստեղծել աղյուսակ",
            }
          : preview.upToDate
            ? {
                title: "Աղյուսակն արդեն թարմ է",
                description: `«${preview.table.name}» աղյուսակում արդեն կան բոլոր իրադարձությունները։ Եթե Google-ում նոր պատվեր եք ավելացրել, նախ սեղմեք «Համաժամեցնել»։`,
                details:
                  preview.keptEdited > 0
                    ? [{ label: "Ձեռքով խմբագրված տողեր (պահպանվում են)", value: String(preview.keptEdited) }]
                    : undefined,
                confirmLabel: "Բացել աղյուսակը",
                cancelLabel: "Փակել",
              }
            : {
                title: "Ստեղծե՞լ աղյուսակի նոր տարբերակ",
                description: `Google-ում փոփոխություններ կան։ Կստեղծվի նոր աղյուսակ՝ թարմ տվյալներով, իսկ «${preview.table.name}»-ը կպահվի որպես հին տարբերակ, որպեսզի ոչինչ չկորչի։ Ձեռքով խմբագրված և ավելացված տողերը կանցնեն նոր տարբերակին անփոփոխ։`,
                details: [
                  { label: "Նոր իրադարձություններ", value: String(preview.new) },
                  { label: "Փոփոխված", value: String(preview.changed) },
                  { label: "Ջնջված Google-ում", value: String(preview.removed) },
                  ...(preview.keptEdited > 0
                    ? [{ label: "Ձեռքով խմբագրված (պահպանվում են)", value: String(preview.keptEdited) }]
                    : []),
                ],
                confirmLabel: "Ստեղծել նոր տարբերակ",
              }
        : asking === "disconnect"
          ? {
              title: "Անջատե՞լ Google օրացույցը",
              description:
                "Google-ի իրադարձությունները կհեռանան այս օրացույցից, իսկ մենք կմոռանանք ձեր հաշվի մուտքը։ Դրանցից ստեղծված աղյուսակները կմնան։",
              confirmLabel: "Անջատել",
              tone: "danger",
            }
          : null;

  return (
    <Card className="space-y-3">
      {confirm ? (
        <ConfirmDialog
          open
          content={confirm}
          pending={pending}
          error={error}
          onConfirm={() => (asking === "sync" ? sync() : asking === "table" ? makeTable() : disconnect())}
          onCancel={() => {
            setAsking(null);
            setError(null);
          }}
        />
      ) : null}

      {picking && connection ? (
        <CalendarPicker
          state={state}
          onClose={() => setPicking(false)}
          onSaved={(any) => {
            setPicking(false);
            // Calendars just chosen are read at once.
            if (any) sync();
            else router.refresh();
          }}
        />
      ) : null}

      <div className="flex flex-wrap items-start gap-3">
        <span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-full border border-border bg-surface">
          <GoogleMark className="h-5 w-5" />
        </span>
        <div className="min-w-0 flex-1">
          <h2 className="font-semibold text-ink">Google օրացույց</h2>
          {!state.configured ? (
            <p className="text-sm text-ink-muted">
              Google օրացույցի հետ համաժամեցումը հարթակում դեռ կարգավորված չէ։{" "}
              {staff ? (
                <span className="text-ink-faint">
                  Ավելացրեք GOOGLE_CLIENT_ID, GOOGLE_CLIENT_SECRET և GOOGLE_REDIRECT_URI՝ api/.env-ում։
                </span>
              ) : (
                <Link href="/admin/support" className="font-medium text-primary underline">
                  Գրեք մեզ
                </Link>
              )}
            </p>
          ) : !connection ? (
            <p className="text-sm text-ink-muted">
              Միացրեք ձեր Google հաշիվը և ընտրեք օրացույցները. դրանցում նշված պատվերներն ու հանդիպումները կերևան այստեղ։
              Մենք միայն կարդում ենք ձեր օրացույցը և ոչինչ չենք փոխում։
            </p>
          ) : connection.needsReconnect ? (
            <p className="text-sm text-warning">
              Google-ը այլևս թույլ չի տալիս կարդալ {connection.email ?? "հաշվի"} օրացույցը։ Միացրեք հաշիվը նորից։
            </p>
          ) : (
            <p className="text-sm text-ink-muted">
              {connection.email}
              {" · "}
              {selected.length > 0 ? `${selected.length} օրացույց` : "օրացույցներ ընտրված չեն"}
              {" · "}
              {connection.syncedAt ? `համաժամեցված՝ ${formatDateTime(connection.syncedAt)}` : "դեռ համաժամեցված չէ"}
            </p>
          )}
        </div>

        <div className="flex flex-wrap items-center gap-2">
          {state.configured && (!connection || connection.needsReconnect) ? (
            // A full page visit: off to Google and back.
            <a href="/api/admin/google/connect" className={buttonClasses("primary", "sm")}>
              {connection ? "Միացնել նորից" : "Միացնել Google օրացույցը"}
            </a>
          ) : null}

          {connection && !connection.needsReconnect ? (
            <>
              <Button
                size="sm"
                onClick={() => (selected.length > 0 ? setAsking("sync") : setPicking(true))}
                disabled={pending}
              >
                <RefreshCw className={cn("h-4 w-4", pending && asking === null && "animate-spin")} />
                Համաժամեցնել
              </Button>
              {canMakeTable ? (
                <Button
                  size="sm"
                  variant="secondary"
                  onClick={askTable}
                  disabled={pending || (state.pending.events === 0 && !state.pending.table && !state.pending.spread?.length)}
                >
                  <Table2 className="h-4 w-4" />
                  Դարձնել աղյուսակ
                  {state.pending.events > 0 && !state.pending.upToDate ? (
                    <Badge tone="primary" className="ml-0.5 px-1.5">
                      {state.pending.table || state.pending.spread?.length ? changes(state.pending) : state.pending.events}
                    </Badge>
                  ) : null}
                </Button>
              ) : null}
              <Button size="sm" variant="ghost" onClick={() => setPicking(true)} aria-label="Ընտրել օրացույցները">
                <CalendarCog className="h-4 w-4" />
                <span className="hidden sm:inline">Օրացույցներ</span>
              </Button>
            </>
          ) : null}

          {connection ? (
            <Button size="sm" variant="ghost" onClick={() => setAsking("disconnect")} aria-label="Անջատել Google օրացույցը">
              <Unplug className="h-4 w-4" />
            </Button>
          ) : null}
        </div>
      </div>

      {connection && !connection.needsReconnect && !state.pending.table && state.pending.spread?.length && !state.pending.upToDate ? (
        <p className="text-xs text-ink-muted">
          Google-ում կան փոփոխություններ․ «Դարձնել աղյուսակ»-ը դրանք կավելացնի ըստ ամիսների աղյուսակներին։
        </p>
      ) : null}
      {connection && !connection.needsReconnect && state.pending.table && !state.pending.upToDate ? (
        <p className="text-xs text-ink-muted">
          Google-ում կան փոփոխություններ, որոնք դեռ «{state.pending.table.name}» աղյուսակում չեն։ «Դարձնել աղյուսակ»-ը կստեղծի նոր տարբերակ։
        </p>
      ) : null}

      {message ? (
        <p
          role="status"
          className={cn(
            "flex flex-wrap items-center gap-x-2 rounded-md px-3 py-2 text-sm",
            message.tone === "success" ? "bg-success-light text-success" : "bg-danger-light text-danger",
          )}
        >
          {message.text}
          {message.table ? (
            <Link href={tablePath("orders", message.table)} className="font-medium underline">
              Բացել աղյուսակը
            </Link>
          ) : null}
        </p>
      ) : null}
    </Card>
  );
}

/** Which of the account's calendars to sync: those with the business's orders and meetings. */
function CalendarPicker({
  state,
  onClose,
  onSaved,
}: {
  state: GoogleStateDto;
  onClose: () => void;
  onSaved: (anySelected: boolean) => void;
}) {
  const titleId = useId();
  const calendars = state.connection?.calendars ?? [];
  const [chosen, setChosen] = useState(() => new Set(calendars.filter((c) => c.selected).map((c) => c.id)));
  const [pending, setPending] = useState(false);
  const [failed, setFailed] = useState(false);

  function toggle(id: number) {
    setChosen((s) => {
      const next = new Set(s);
      if (next.has(id)) next.delete(id);
      else next.add(id);
      return next;
    });
  }

  async function save() {
    setPending(true);
    setFailed(false);
    try {
      const response = await fetch("/api/admin/google/calendars", {
        method: "PUT",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ calendars: [...chosen] }),
      });
      const json = await response.json().catch(() => null);
      if (!response.ok || !json?.data) {
        setFailed(true);
        return;
      }
      onSaved(chosen.size > 0);
    } catch {
      setFailed(true);
    } finally {
      setPending(false);
    }
  }

  return (
    <Dialog open onClose={() => (pending ? undefined : onClose())} labelledBy={titleId}>
      <div className="space-y-4">
        <div className="flex items-start justify-between gap-3">
          <div className="space-y-1">
            <h2 id={titleId} className="text-base font-semibold text-ink">
              Որ օրացույցներից բերել պատվերները
            </h2>
            <p className="text-sm text-ink-muted">
              Ընտրված օրացույցների իրադարձությունները կերևան բիզնեսի օրացույցում բոլոր աշխատակիցներին։ Անձնական օրացույցները
              մի՛ ընտրեք։
            </p>
          </div>
          <DialogClose onClick={onClose} />
        </div>

        {calendars.length === 0 ? (
          <p className="rounded-md bg-surface-secondary px-3 py-2 text-sm text-ink-muted">Այս հաշվում կարդալու օրացույցներ չկան։</p>
        ) : (
          <ul className="divide-y divide-border rounded-lg border border-border">
            {calendars.map((calendar) => (
              <li key={calendar.id}>
                <label className="flex cursor-pointer items-center gap-3 px-3 py-2.5 hover:bg-surface-secondary/60">
                  <input type="checkbox" checked={chosen.has(calendar.id)} onChange={() => toggle(calendar.id)} />
                  <span
                    className="h-3 w-3 shrink-0 rounded-full border border-border"
                    style={{ backgroundColor: calendar.color ?? undefined }}
                  />
                  <span className="min-w-0 flex-1 break-words text-sm text-ink">{calendar.name}</span>
                  {calendar.primary ? <Badge>Հիմնական</Badge> : null}
                  {calendar.selected && calendar.events > 0 ? (
                    <span className="whitespace-nowrap text-xs text-ink-faint">{calendar.events} իրադարձ.</span>
                  ) : null}
                </label>
              </li>
            ))}
          </ul>
        )}

        <p className="text-xs text-ink-faint">
          Կբերենք մեկ տարի առաջ և մեկ տարի հետո ընկած իրադարձությունները։ Ընտրությունից հանված օրացույցի իրադարձությունները
          կհեռանան օրացույցից (աղյուսակները կմնան)։
        </p>

        {failed ? <p className="text-sm text-danger">Չհաջողվեց պահպանել։ Փորձեք կրկին։</p> : null}

        <div className="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
          <Button variant="secondary" onClick={onClose} disabled={pending}>
            Չեղարկել
          </Button>
          <Button onClick={save} disabled={pending}>
            <Check className="h-4 w-4" />
            {pending ? "Մի պահ…" : chosen.size > 0 ? "Պահպանել և համաժամեցնել" : "Պահպանել"}
          </Button>
        </div>
      </div>
    </Dialog>
  );
}
