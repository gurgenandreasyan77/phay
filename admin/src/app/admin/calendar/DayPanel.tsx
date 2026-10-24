"use client";

import { useState, type CSSProperties } from "react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { ExternalLink, MapPin, Phone, Plus, ShoppingBag, Trash2, Wrench } from "lucide-react";
import type { AdminDataTableDto, CalendarGoogleEventDto, CalendarLeadDto } from "@/lib/adminApi";
import { Badge, Button, buttonClasses, Card, cn, FieldLabel, Select } from "../_components/ui";
import { LEAD_STATUS_LABELS, LEAD_STATUS_TONES } from "../_components/leadStatus";
import { CellInput, fromDraft, toDraft, type Draft } from "../_components/tables/RowsGrid";
import { formatMoney, SECTIONS, tablePath, validationErrors } from "../_components/tables/shared";
import { contains, filingLabel, isDate, periodOf } from "../_components/tables/periods";
import { ConfirmDialog } from "../_components/dialog/ConfirmDialog";
import { DialogClose } from "../_components/dialog/Dialog";
import { GOOGLE_DOT, markShape, type CalendarEntry } from "./entries";
import styles from "./calendar.module.css";

/** The table a new order went to last time, remembered in this browser. */
const LAST_TABLE_KEY = "servio.calendar.table";

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

/**
 * A new row of the chosen table, on this day: the table's own fields. When
 * the chosen table is kept for another day, week, month, quarter or year,
 * the API puts the row in the table of the date's period, made like the
 * chosen one when there is none.
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
  const [tableId, setTableId] = useState<number>(() => {
    const remembered = tables.find((t) => t.id === lastTable(tables));
    // The table of this day's period, like the one used last time if there are several.
    const ofDay = tables.filter((t) => t.periodInfo && contains(t.periodInfo, day));
    return (
      ofDay.find((t) => t.periodInfo?.kind === remembered?.periodInfo?.kind)?.id ??
      ofDay[0]?.id ??
      remembered?.id ??
      tables[0].id
    );
  });
  const table = tables.find((t) => t.id === tableId) ?? tables[0];
  const [draft, setDraft] = useState<Draft>(() => toDraft(table, undefined, day));
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [pending, setPending] = useState(false);
  const [failed, setFailed] = useState(false);

  // Where the row will go, by the period of the date typed in.
  const date = table.dateColumn ? (draft[table.dateColumn] ?? "").slice(0, 10) : "";
  const period = table.periodInfo;
  const elsewhere = isDate(date) && period !== null && !contains(period, date);
  const target =
    elsewhere && period
      ? tables.find((t) => t.periodInfo?.kind === period.kind && contains(t.periodInfo, date))
      : undefined;

  function chooseTable(id: number) {
    const next = tables.find((t) => t.id === id);
    if (!next) return;
    setTableId(id);
    setDraft(toDraft(next, undefined, day));
    setErrors({});
  }

  async function save() {
    setPending(true);
    setErrors({});
    setFailed(false);
    try {
      const response = await fetch("/api/admin/calendar/rows", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ table: table.id, values: fromDraft(table, draft) }),
      });
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
      {tables.length > 1 ? (
        <label className="block space-y-1">
          <FieldLabel>Աղյուսակ</FieldLabel>
          <Select value={table.id} onChange={(e) => chooseTable(Number(e.target.value))}>
            {(["orders", "sales"] as const).map((section) => {
              const inSection = tables.filter((t) => t.section === section);
              if (inSection.length === 0) return null;
              return (
                <optgroup key={section} label={section === "orders" ? "Պատվերներ (ծառայություններ)" : "Վաճառքներ"}>
                  {inSection.map((t) => (
                    <option key={t.id} value={t.id}>
                      {t.name}
                      {t.periodInfo && t.periodInfo.label !== t.name ? ` · ${t.periodInfo.label}` : ""}
                    </option>
                  ))}
                </optgroup>
              );
            })}
          </Select>
        </label>
      ) : (
        <p className="text-xs text-ink-muted">Աղյուսակ՝ {table.name}</p>
      )}

      {table.columns.map((column) => (
        <label key={column.key} className="block space-y-1">
          <FieldLabel>
            {column.label}
            {column.required || column.key === table.dateColumn ? <span className="text-danger"> *</span> : null}
          </FieldLabel>
          <CellInput
            column={column}
            value={draft[column.key] ?? ""}
            onChange={(value) => setDraft((d) => ({ ...d, [column.key]: value }))}
          />
          {errors[`values.${column.key}`] ? <p className="text-xs text-danger">{errors[`values.${column.key}`]}</p> : null}
        </label>
      ))}

      {isDate(date) ? <p className="text-xs text-ink-faint">Կգրանցվի՝ {filingLabel(date)}</p> : null}

      {elsewhere && period ? (
        <p className="rounded-md bg-surface px-3 py-2 text-xs text-ink-muted">
          {target
            ? `Ամսաթիվը «${table.name}» աղյուսակի ժամանակահատվածից (${period.label}) դուրս է։ ${table.section === "sales" ? "Վաճառքը" : "Պատվերը"} կավելացվի «${target.name}» աղյուսակում։`
            : `«${periodOf(period.kind, date).label}» ժամանակահատվածի համար աղյուսակ դեռ չկա։ Կստեղծվի նորը՝ «${table.name}»-ի սյունակներով, և ${table.section === "sales" ? "վաճառքը" : "պատվերը"} կավելացվի այնտեղ։`}
        </p>
      ) : null}

      <div className="flex items-center gap-2">
        <Button type="submit" size="sm" disabled={pending}>
          Պահպանել
        </Button>
        <button type="button" className={buttonClasses("ghost", "sm")} onClick={() => onDone(false)} disabled={pending}>
          Չեղարկել
        </button>
        {failed ? <span className="text-xs text-danger">Չհաջողվեց պահպանել։</span> : null}
      </div>
    </form>
  );
}
