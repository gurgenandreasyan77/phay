"use client";

import { useEffect, useState, type CSSProperties } from "react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { ExternalLink, MapPin, Phone, Plus, ShoppingBag, Trash2, TreePalm, TriangleAlert, UserSearch, Wrench } from "lucide-react";
import type {
  AbsenceDto,
  AdminDataTableDto,
  CalendarGoogleEventDto,
  CalendarInterviewDto,
  CalendarLeadDto,
} from "@/lib/adminApi";
import { Badge, Button, buttonClasses, Card, cn, FieldLabel, Select } from "@/ui";
import { LEAD_STATUS_LABELS, LEAD_STATUS_TONES } from "../_components/leadStatus";
import { CellInput, fromDraft, toDraft, type Draft } from "../_components/tables/RowsGrid";
import { formatMoney, SECTIONS, tablePath, validationErrors } from "../_components/tables/shared";
import { contains, filingLabel, isDate, periodOf } from "../_components/tables/periods";
import { ConfirmDialog } from "@/ui/dialog/ConfirmDialog";
import { DialogClose } from "@/ui/dialog/Dialog";
import { GOOGLE_DOT, markShape, type CalendarEntry } from "./entries";
import styles from "./calendar.module.css";

/** The table a new order went to last time, remembered in this browser. */
const LAST_TABLE_KEY = "phay.calendar.table";

function lastTable(tables: AdminDataTableDto[]): number | undefined {
  try {
    const id = Number(localStorage.getItem(LAST_TABLE_KEY));
    return tables.some((t) => t.id === id) ? id : undefined;
  } catch {
    return undefined;
  }
}

export type DayPanelProps = {
  day: string;
  title: string;
  tables: AdminDataTableDto[];
  /** Rows of the order tables (services done) and of the sales tables, shown apart. */
  services: CalendarEntry[];
  sales: CalendarEntry[];
  leads: CalendarLeadDto[];
  google: CalendarGoogleEventDto[];
  /** Who of the team is away that day, and interviews with candidates. */
  away?: AbsenceDto[];
  interviews?: CalendarInterviewDto[];
  colors: Record<number, string>;
  canDesign: boolean;
};

/**
 * One day of the calendar: its services and its sales apart, each with how
 * many and how much, adding a row to a table and deleting one. `onClose`
 * puts it in the phone's sheet, with a cross instead of a card.
 */
export function DayPanel({
  day,
  title,
  tables,
  services,
  sales,
  leads,
  google,
  away = [],
  interviews = [],
  colors,
  canDesign,
  onClose,
}: DayPanelProps & { onClose?: () => void }) {
  const router = useRouter();
  const [adding, setAdding] = useState(false);
  const [asking, setAsking] = useState<CalendarEntry | null>(null);
  const [deleting, setDeleting] = useState(false);
  const [failed, setFailed] = useState(false);
  const sum = (rows: CalendarEntry[]) => rows.reduce((total, e) => total + (e.amount ?? 0), 0);
  const servicesAmount = sum(services) + leads.reduce((total, l) => total + (l.finalPrice ?? 0), 0);
  const salesAmount = sum(sales);
  const total = servicesAmount + salesAmount;
  const serviceCount = services.length + leads.length + google.length;
  const hasSales = sales.length > 0 || tables.some((t) => t.section === "sales");
  const hasServices = serviceCount > 0 || tables.some((t) => t.section === "orders");

  async function remove(entry: CalendarEntry) {
    setDeleting(true);
    setFailed(false);
    try {
      const response = await fetch(`/api/admin/data-tables/${entry.tableId}/rows/${entry.id}`, { method: "DELETE" });
      if (!response.ok) {
        setFailed(true);
        return;
      }
      setAsking(null);
      router.refresh();
    } catch {
      setFailed(true);
    } finally {
      setDeleting(false);
    }
  }

  const Wrapper = onClose ? "div" : Card;

  return (
    <Wrapper className={onClose ? "space-y-4" : "space-y-4 self-start lg:sticky lg:top-4"}>
      {asking ? (
        <ConfirmDialog
          open
          content={{
            title: asking.section === "sales" ? "Ջնջե՞լ վաճառքը" : "Ջնջե՞լ պատվերը",
            description: `${asking.section === "sales" ? "Վաճառքը" : "Պատվերը"} կհեռացվի աղյուսակից և վերլուծությունից։ Այս գործողությունը հետ չի շրջվում։`,
            details: [
              { label: asking.section === "sales" ? "Վաճառք" : "Պատվեր", value: asking.title },
              { label: "Աղյուսակ", value: asking.table.name },
              ...(asking.amount !== null ? [{ label: "Գումար", value: formatMoney(asking.amount) }] : []),
            ],
            confirmLabel: "Այո, ջնջել",
            tone: "danger",
          }}
          pending={deleting}
          error={failed ? "Չհաջողվեց ջնջել։ Փորձեք կրկին։" : null}
          onConfirm={() => remove(asking)}
          onCancel={() => {
            setAsking(null);
            setFailed(false);
          }}
        />
      ) : null}

      <div className="flex items-start justify-between gap-3">
        <div>
          <h3 className="font-semibold text-ink first-letter:uppercase">{title}</h3>
          <p className="mt-0.5 text-sm text-ink-muted">
            {[
              hasServices || serviceCount > 0 ? `${serviceCount} պատվեր` : null,
              hasSales ? `${sales.length} վաճառք` : null,
            ]
              .filter(Boolean)
              .join(" · ")}
            {total > 0 ? ` · ${formatMoney(total)}` : ""}
          </p>
        </div>
        <div className="flex items-center gap-2">
          {tables.length > 0 && !adding ? (
            <Button size="sm" onClick={() => setAdding(true)}>
              <Plus className="h-4 w-4" />
              Ավելացնել
            </Button>
          ) : null}
          {onClose ? <DialogClose onClick={onClose} /> : null}
        </div>
      </div>

      {adding ? (
        <NewOrderForm
          day={day}
          tables={tables}
          onDone={(saved) => {
            setAdding(false);
            if (saved) router.refresh();
          }}
        />
      ) : null}

      {hasServices && hasSales && (serviceCount > 0 || sales.length > 0) ? (
        <div className="grid grid-cols-2 gap-2">
          <div className="rounded-md border border-primary/25 bg-primary-light/50 px-3 py-2">
            <p className="flex items-center gap-1.5 text-xs font-medium text-primary">
              <Wrench className="h-3.5 w-3.5" />
              Ծառայություններ
            </p>
            <p className="mt-0.5 text-lg font-semibold text-ink">{serviceCount}</p>
            <p className="text-xs text-ink-muted">{servicesAmount > 0 ? formatMoney(servicesAmount) : "—"}</p>
          </div>
          <div className="rounded-md border border-warning/30 bg-warning-light/70 px-3 py-2">
            <p className="flex items-center gap-1.5 text-xs font-medium text-warning">
              <ShoppingBag className="h-3.5 w-3.5" />
              Վաճառքներ
            </p>
            <p className="mt-0.5 text-lg font-semibold text-ink">{sales.length}</p>
            <p className="text-xs text-ink-muted">{salesAmount > 0 ? formatMoney(salesAmount) : "—"}</p>
          </div>
        </div>
      ) : null}

      {away.length > 0 || interviews.length > 0 ? <TeamDay away={away} interviews={interviews} /> : null}

      {tables.length === 0 ? (
        <p className="rounded-md bg-surface-secondary px-3 py-2 text-sm text-ink-muted">
          Օրացույցից պատվեր կամ վաճառք ավելացնելու համար պետք է աղյուսակ՝ «Ամսաթիվ» դաշտով։{" "}
          {canDesign ? (
            <Link href={tablePath("orders")} className="font-medium text-primary underline">
              Ստեղծել աղյուսակ
            </Link>
          ) : null}
        </p>
      ) : null}

      {services.length === 0 && sales.length === 0 && leads.length === 0 && google.length === 0 && !adding ? (
        <p className="py-6 text-center text-sm text-ink-muted">
          {hasSales ? "Այս օրը պատվերներ և վաճառքներ չկան։" : "Այս օրը պատվերներ չկան։"}
        </p>
      ) : null}

      {sales.length > 0 && serviceCount > 0 ? (
        <p className="flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wide text-primary">
          <Wrench className="h-3.5 w-3.5" />
          Ծառայություններ
        </p>
      ) : null}
      <ul className="divide-y divide-border">
        {services.map((entry, j) => (
          <li key={entry.id} className={cn(styles.item, "flex items-start gap-3 py-3")} style={{ "--j": j } as CSSProperties}>
            <span className="w-11 shrink-0 pt-0.5 text-sm font-medium text-ink">{entry.time ?? "—"}</span>
            <div className="min-w-0 flex-1">
              <p className="break-words font-medium text-ink">{entry.title}</p>
              {entry.customer || entry.phone ? (
                <p className="mt-0.5 flex flex-wrap items-center gap-x-2 text-sm text-ink-muted">
                  {entry.customer ? <span>{entry.customer}</span> : null}
                  {entry.phone ? (
                    <a href={`tel:${entry.phone.replace(/[^\d+]/g, "")}`} className="inline-flex items-center gap-1 hover:text-ink">
                      <Phone className="h-3 w-3" />
                      {entry.phone}
                    </a>
                  ) : null}
                </p>
              ) : null}
              {entry.address ? <p className="mt-0.5 text-sm text-ink-muted">{entry.address}</p> : null}
              <p className="mt-1 flex flex-wrap items-center gap-2 text-xs text-ink-muted">
                <Link href={tablePath("orders", entry.tableId)} className="inline-flex items-center gap-1 hover:text-ink">
                  <span className={cn("h-2 w-2", markShape("orders"), colors[entry.tableId])} />
                  {entry.table.name}
                </Link>
                {entry.status ? <Badge>{entry.status}</Badge> : null}
                {entry.source === "google" ? <Badge tone="info">Google</Badge> : null}
              </p>
            </div>
            <div className="flex shrink-0 flex-col items-end gap-1">
              {entry.amount !== null ? <span className="whitespace-nowrap text-sm font-medium text-ink">{formatMoney(entry.amount)}</span> : null}
              <Button
                variant="ghost"
                size="sm"
                aria-label="Ջնջել"
                disabled={deleting && asking?.id === entry.id}
                onClick={() => setAsking(entry)}
              >
                <Trash2 className="h-4 w-4" />
              </Button>
            </div>
          </li>
        ))}
        {leads.map((lead, j) => (
          <li
            key={`lead-${lead.id}`}
            className={cn(styles.item, "flex items-start gap-3 py-3")}
            style={{ "--j": services.length + j } as CSSProperties}
          >
            <span className="w-11 shrink-0 pt-0.5 text-sm text-info">Կայք</span>
            <div className="min-w-0 flex-1">
              <p className="break-words font-medium text-ink">{lead.service ?? "Պատվեր կայքից"}</p>
              <p className="mt-0.5 flex flex-wrap items-center gap-x-2 text-sm text-ink-muted">
                <span>{lead.name}</span>
                <a href={`tel:${lead.phone.replace(/[^\d+]/g, "")}`} className="inline-flex items-center gap-1 hover:text-ink">
                  <Phone className="h-3 w-3" />
                  {lead.phone}
                </a>
              </p>
              <p className="mt-1 flex flex-wrap items-center gap-2 text-xs">
                <Badge tone={LEAD_STATUS_TONES[lead.status]}>{LEAD_STATUS_LABELS[lead.status]}</Badge>
                <Link href={`${SECTIONS.orders.base}/site`} className="text-ink-muted hover:text-ink">
                  Կայքի պատվերներ →
                </Link>
              </p>
            </div>
            {lead.finalPrice !== null ? (
              <span className="shrink-0 whitespace-nowrap text-sm font-medium text-ink">{formatMoney(lead.finalPrice)}</span>
            ) : null}
          </li>
        ))}
        {google.map((event, j) => (
          <li
            key={`google-${event.id}`}
            className={cn(styles.item, "flex items-start gap-3 py-3")}
            style={{ "--j": services.length + leads.length + j } as CSSProperties}
          >
            <span className="w-11 shrink-0 pt-0.5 text-sm font-medium text-ink">{event.time ?? "—"}</span>
            <div className="min-w-0 flex-1">
              <p className="break-words font-medium text-ink">{event.title}</p>
              {event.time && event.end ? (
                <p className="mt-0.5 text-sm text-ink-muted">
                  {event.time}–{event.end}
                </p>
              ) : !event.time ? (
                <p className="mt-0.5 text-sm text-ink-muted">Ամբողջ օրը</p>
              ) : null}
              {event.location ? (
                <p className="mt-0.5 flex items-center gap-1 text-sm text-ink-muted">
                  <MapPin className="h-3 w-3 shrink-0" />
                  <span className="break-words">{event.location}</span>
                </p>
              ) : null}
              {event.description ? (
                <p className="mt-0.5 line-clamp-3 whitespace-pre-line break-words text-sm text-ink-muted">{event.description}</p>
              ) : null}
              <p className="mt-1 flex flex-wrap items-center gap-2 text-xs text-ink-muted">
                <span className="inline-flex items-center gap-1">
                  <span className={cn("h-2 w-2 rounded-full", GOOGLE_DOT)} />
                  Google · {event.calendar}
                </span>
                {event.link ? (
                  <a href={event.link} target="_blank" rel="noopener noreferrer" className="inline-flex items-center gap-1 hover:text-ink">
                    Բացել Google-ում
                    <ExternalLink className="h-3 w-3" />
                  </a>
                ) : null}
              </p>
            </div>
          </li>
        ))}
      </ul>
      {sales.length > 0 ? (
        <>
          <p className="flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wide text-warning">
            <ShoppingBag className="h-3.5 w-3.5" />
            Վաճառքներ · {sales.length}
            {salesAmount > 0 ? ` · ${formatMoney(salesAmount)}` : ""}
          </p>
          <ul className="space-y-2">
            {sales.map((entry, j) => (
              <li
                key={entry.id}
                className={cn(styles.item, "flex items-start gap-3 rounded-md border-l-[3px] border-chart-3 bg-warning-light/50 px-3 py-2")}
                style={{ "--j": services.length + leads.length + google.length + j } as CSSProperties}
              >
                <div className="min-w-0 flex-1">
                  <p className="break-words font-medium text-ink">
                    {entry.title}
                    {entry.quantity ? <span className="font-normal text-ink-muted"> × {entry.quantity}</span> : null}
                  </p>
                  {entry.details ? <p className="mt-0.5 text-sm text-ink-muted">{entry.details}</p> : null}
                  <p className="mt-1 flex flex-wrap items-center gap-2 text-xs text-ink-muted">
                    {entry.time ? <span>{entry.time}</span> : null}
                    <Link href={tablePath("sales", entry.tableId)} className="inline-flex items-center gap-1 hover:text-ink">
                      <span className={cn("h-2 w-2", markShape("sales"), colors[entry.tableId])} />
                      {entry.table.name}
                    </Link>
                    {entry.status ? <Badge>{entry.status}</Badge> : null}
                  </p>
                </div>
                <div className="flex shrink-0 flex-col items-end gap-1">
                  {entry.amount !== null ? <span className="whitespace-nowrap text-sm font-medium text-ink">{formatMoney(entry.amount)}</span> : null}
                  <Button
                    variant="ghost"
                    size="sm"
                    aria-label="Ջնջել"
                    disabled={deleting && asking?.id === entry.id}
                    onClick={() => setAsking(entry)}
                  >
                    <Trash2 className="h-4 w-4" />
                  </Button>
                </div>
              </li>
            ))}
          </ul>
        </>
      ) : null}
      {google.length > 0 ? (
        <p className="text-xs text-ink-faint">
          Google-ի իրադարձությունները դեռ աղյուսակում չեն և չեն հաշվվում վերլուծության մեջ։ Սեղմեք «Դարձնել աղյուսակ» օրացույցի վերևում։
        </p>
      ) : null}
    </Wrapper>
  );
}

/** The team that day: who is away (and till when), and the interviews. */
function TeamDay({ away, interviews }: { away: AbsenceDto[]; interviews: CalendarInterviewDto[] }) {
  const months = ["հունվարի", "փետրվարի", "մարտի", "ապրիլի", "մայիսի", "հունիսի", "հուլիսի", "օգոստոսի", "սեպտեմբերի", "հոկտեմբերի", "նոյեմբերի", "դեկտեմբերի"];
  const until = (date: string) => `${months[Number(date.slice(5, 7)) - 1]} ${Number(date.slice(8, 10))}`;

  return (
    <div className="space-y-1.5 rounded-md border border-[#8b5cf6]/25 bg-[#8b5cf6]/5 px-3 py-2">
      {away.map((a) => (
        <Link
          key={`away-${a.id}`}
          href="/admin/employees?tab=absences"
          className="flex items-start gap-2 text-sm hover:text-primary"
        >
          <TreePalm className="mt-0.5 h-4 w-4 shrink-0 text-[#7c3aed]" />
          <span className="min-w-0">
            <span className="font-medium text-ink">{a.employee?.name}</span>
            <span className="text-ink-muted">
              {" "}
              · {a.kindLabel.toLowerCase()}
              {a.from !== a.to ? `, մինչև ${until(a.to)}` : ""}
            </span>
          </span>
        </Link>
      ))}
      {interviews.map((v) => (
        <Link
          key={`interview-${v.id}`}
          href="/admin/employees?tab=hiring"
          className="flex items-start gap-2 text-sm hover:text-primary"
        >
          <UserSearch className="mt-0.5 h-4 w-4 shrink-0 text-[#7c3aed]" />
          <span className="min-w-0">
            <span className="text-ink-muted">{v.time} </span>
            <span className="font-medium text-ink">Հարցազրույց՝ {v.name}</span>
            {v.opening ? <span className="text-ink-muted"> · {v.opening}</span> : null}
          </span>
        </Link>
      ))}
    </div>
  );
}

type Section = "orders" | "sales";

const ADDING: Record<Section, { label: string; hint: string; noun: string }> = {
  orders: { label: "Պատվեր", hint: "ծառայություն", noun: "Պատվերը" },
  sales: { label: "Վաճառք", hint: "ապրանք", noun: "Վաճառքը" },
};

/** A performer who isn't free then (PerformerAvailability on the API). */
type Conflict = { kind: "away" | "busy"; employee: string; message: string; free: string[] };

/**
 * The table of the section a new row of this day goes to: the one used
 * last time when it holds the day (or any dates), else the table of the
 * day's period, else the latest period's table (the API then makes the
 * day's one, like it), else the first.
 */
function currentTable(inSection: AdminDataTableDto[], day: string, rememberedId?: number): AdminDataTableDto {
  const remembered = inSection.find((t) => t.id === rememberedId);
  if (remembered && (!remembered.periodInfo || contains(remembered.periodInfo, day))) return remembered;
  const ofDay = inSection.filter((t) => t.periodInfo && contains(t.periodInfo, day));
  const latest = inSection
    .filter((t) => t.periodInfo)
    .sort((a, b) => (b.periodInfo?.start ?? "").localeCompare(a.periodInfo?.start ?? ""))[0];
  return (
    ofDay.find((t) => t.periodInfo?.kind === remembered?.periodInfo?.kind) ??
    ofDay[0] ??
    (remembered?.periodInfo ? undefined : remembered) ??
    inSection.find((t) => !t.periodInfo) ??
    latest ??
    inSection[0]
  );
}

/**
 * A new order or sale on this day. With tables of both, it asks which one
 * first; then shows the fields of that section's current table. When the
 * date is out of the table's period, the API puts the row in the table of
 * the date's period, made like this one when there is none. An empty order
 * number gets the next one; a performer who is away or on another order
 * then is told while typing, and saving asks to confirm.
 */
function NewOrderForm({
  day,
  tables,
  onDone,
}: {
  day: string;
  tables: AdminDataTableDto[];
  onDone: (saved: boolean) => void;
}) {
  const sections = (["orders", "sales"] as const).filter((s) => tables.some((t) => t.section === s));
  const [section, setSection] = useState<Section | null>(() => (sections.length === 1 ? sections[0] : null));
  const [tableId, setTableId] = useState<number | null>(() =>
    sections.length === 1 ? currentTable(tables.filter((t) => t.section === sections[0]), day, lastTable(tables)).id : null,
  );
  const inSection = tables.filter((t) => t.section === section);
  const table = tables.find((t) => t.id === tableId) ?? null;
  const [draft, setDraft] = useState<Draft>(() => (table ? toDraft(table, undefined, day) : {}));
  const [choosing, setChoosing] = useState(false);
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [conflicts, setConflicts] = useState<Conflict[]>([]);
  const [orderNumber, setOrderNumber] = useState<string | null>(null);
  const [pending, setPending] = useState(false);
  const [failed, setFailed] = useState(false);

  // What the performer and the number depend on: who, when, how long.
  const watched = table
    ? table.columns
        .filter((c) => c.key === table.dateColumn || ["performer", "time", "duration", "order_number"].includes(c.role ?? ""))
        .map((c) => draft[c.key] ?? "")
        .join("|")
    : "";
  const checks = table !== null && table.columns.some((c) => c.role === "performer" || c.role === "order_number");

  useEffect(() => {
    if (!table || !checks) return;
    const controller = new AbortController();
    const timer = setTimeout(async () => {
      try {
        const response = await fetch("/api/admin/calendar/rows/check", {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({ table: table.id, values: fromDraft(table, draft) }),
          signal: controller.signal,
        });
        if (!response.ok) return;
        const body = (await response.json()) as { data: { conflicts: Conflict[]; orderNumber: string | null } };
        setConflicts(body.data.conflicts);
        setOrderNumber(body.data.orderNumber);
      } catch {
        // Only a hint: saving checks again.
      }
    }, 350);
    return () => {
      clearTimeout(timer);
      controller.abort();
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps -- only who, when and how long matter
  }, [table?.id, watched, checks]);

  function chooseSection(next: Section) {
    const chosen = currentTable(tables.filter((t) => t.section === next), day, lastTable(tables));
    setSection(next);
    pickTable(chosen);
  }

  function pickTable(next: AdminDataTableDto) {
    setTableId(next.id);
    setDraft(toDraft(next, undefined, day));
    setErrors({});
    setConflicts([]);
    setOrderNumber(null);
    setChoosing(false);
  }

  // Where the row will go, by the period of the date typed in.
  const date = table?.dateColumn ? (draft[table.dateColumn] ?? "").slice(0, 10) : "";
  const period = table?.periodInfo ?? null;
  const elsewhere = isDate(date) && period !== null && !contains(period, date);
  const target =
    elsewhere && period
      ? inSection.find((t) => t.periodInfo?.kind === period.kind && contains(t.periodInfo, date))
      : undefined;
  const noun = section ? ADDING[section].noun : "";

  async function save() {
    if (!table) return;
    setPending(true);
    setErrors({});
    setFailed(false);
    try {
      const response = await fetch("/api/admin/calendar/rows", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        // The owner saw who isn't free and saves anyway.
        body: JSON.stringify({ table: table.id, values: fromDraft(table, draft), anyway: conflicts.length > 0 }),
      });
      if (response.status === 409) {
        const body = (await response.json().catch(() => null)) as { conflicts?: Conflict[] } | null;
        setConflicts(body?.conflicts ?? []);
        return;
      }
      if (response.status === 422) {
        setErrors(validationErrors(await response.json().catch(() => null)));
        return;
      }
      if (!response.ok) {
        setFailed(true);
        return;
      }
      try {
        localStorage.setItem(LAST_TABLE_KEY, String(table.id));
      } catch {
        // Only a convenience.
      }
      onDone(true);
    } catch {
      setFailed(true);
    } finally {
      setPending(false);
    }
  }

  return (
    <form
      className="space-y-3 rounded-md border border-primary/40 bg-primary-light/40 p-3"
      onSubmit={(e) => {
        e.preventDefault();
        save();
      }}
    >
      {sections.length > 1 ? (
        <div className="space-y-1.5">
          <FieldLabel>Ի՞նչ ենք ավելացնում</FieldLabel>
          <div role="radiogroup" aria-label="Ի՞նչ ենք ավելացնում" className="grid grid-cols-2 gap-2">
            {sections.map((s) => {
              const Icon = s === "orders" ? Wrench : ShoppingBag;
              const active = section === s;
              return (
                <button
                  key={s}
                  type="button"
                  role="radio"
                  aria-checked={active}
                  onClick={() => chooseSection(s)}
                  className={cn(
                    "flex items-center gap-2 rounded-md border px-3 py-2 text-left transition-colors",
                    active
                      ? s === "orders"
                        ? "border-primary bg-primary-light text-primary"
                        : "border-warning bg-warning-light text-warning"
                      : "border-border bg-surface text-ink hover:bg-surface-secondary",
                  )}
                >
                  <Icon className="h-4 w-4 shrink-0" />
                  <span className="min-w-0">
                    <span className="block text-sm font-semibold">{ADDING[s].label}</span>
                    <span className="block text-xs text-ink-muted">{ADDING[s].hint}</span>
                  </span>
                </button>
              );
            })}
          </div>
        </div>
      ) : null}

      {table ? (
        <>
          <div className="flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-ink-muted">
            <span>
              Աղյուսակ՝ <span className="font-medium text-ink">{table.name}</span>
              {table.periodInfo && table.periodInfo.label !== table.name ? ` · ${table.periodInfo.label}` : ""}
            </span>
            {inSection.length > 1 && !choosing ? (
              <button type="button" className="font-medium text-primary underline" onClick={() => setChoosing(true)}>
                Փոխել
              </button>
            ) : null}
          </div>
          {choosing ? (
            <Select
              aria-label="Աղյուսակ"
              value={table.id}
              onChange={(e) => {
                const next = inSection.find((t) => t.id === Number(e.target.value));
                if (next) pickTable(next);
              }}
            >
              {inSection.map((t) => (
                <option key={t.id} value={t.id}>
                  {t.name}
                  {t.periodInfo && t.periodInfo.label !== t.name ? ` · ${t.periodInfo.label}` : ""}
                </option>
              ))}
            </Select>
          ) : null}

          {table.columns.map((column) => (
            <label key={column.key} className="block space-y-1">
              <FieldLabel>
                {column.label}
                {(column.required && column.role !== "order_number") || column.key === table.dateColumn ? (
                  <span className="text-danger"> *</span>
                ) : null}
              </FieldLabel>
              <CellInput
                column={column}
                section={table.section}
                value={draft[column.key] ?? ""}
                onChange={(value) => setDraft((d) => ({ ...d, [column.key]: value }))}
                placeholder={column.role === "order_number" ? `Ինքնաշխատ${orderNumber ? `՝ ${orderNumber}` : ""}` : undefined}
              />
              {column.role === "order_number" && !(draft[column.key] ?? "") ? (
                <p className="text-xs text-ink-faint">Դատարկ թողնելիս համարը կտրվի ինքնաշխատ։</p>
              ) : null}
              {errors[`values.${column.key}`] ? <p className="text-xs text-danger">{errors[`values.${column.key}`]}</p> : null}
            </label>
          ))}

          {isDate(date) ? <p className="text-xs text-ink-faint">Կգրանցվի՝ {filingLabel(date)}</p> : null}

          {elsewhere && period ? (
            <p className="rounded-md bg-surface px-3 py-2 text-xs text-ink-muted">
              {target
                ? `Ամսաթիվը «${table.name}» աղյուսակի ժամանակահատվածից (${period.label}) դուրս է։ ${noun} կավելացվի «${target.name}» աղյուսակում։`
                : `«${periodOf(period.kind, date).label}» ժամանակահատվածի համար աղյուսակ դեռ չկա։ Կստեղծվի նորը՝ «${table.name}»-ի սյունակներով, և ${noun.toLowerCase()} կավելացվի այնտեղ։`}
            </p>
          ) : null}

          {conflicts.length > 0 ? (
            <div role="alert" className="space-y-1 rounded-md border border-warning/40 bg-warning-light px-3 py-2 text-sm text-ink">
              {conflicts.map((c) => (
                <p key={c.message} className="flex items-start gap-2">
                  <TriangleAlert className="mt-0.5 h-4 w-4 shrink-0 text-warning" />
                  <span>{c.message}</span>
                </p>
              ))}
            </div>
          ) : null}

          <div className="flex flex-wrap items-center gap-2">
            <Button type="submit" size="sm" variant={conflicts.length > 0 ? "secondary" : "primary"} disabled={pending}>
              {conflicts.length > 0 ? "Պահպանել, միևնույն է" : "Պահպանել"}
            </Button>
            <button type="button" className={buttonClasses("ghost", "sm")} onClick={() => onDone(false)} disabled={pending}>
              Չեղարկել
            </button>
            {failed ? <span className="text-xs text-danger">Չհաջողվեց պահպանել։</span> : null}
          </div>
        </>
      ) : (
        <div className="flex items-center gap-2">
          <p className="flex-1 text-xs text-ink-muted">Ընտրեք՝ պատվեր, թե վաճառք, և կբացվեն այդ աղյուսակի դաշտերը։</p>
          <button type="button" className={buttonClasses("ghost", "sm")} onClick={() => onDone(false)}>
            Չեղարկել
          </button>
        </div>
      )}
    </form>
  );
}
