"use client";

import { useState } from "react";
import { useRouter } from "next/navigation";
import { ArrowDown, ArrowUp, Trash2 } from "lucide-react";
import type { AdminDataColumnDto, AdminDataTableDto, DataColumnType, DataTableSection } from "@/lib/adminApi";
import { Button, Card, FieldLabel, Input, Select, Textarea } from "../ui";
import { COLUMN_TYPE_LABELS, COLUMN_TYPES, tablePath, tablesPath, validationErrors } from "./shared";

type ColumnDraft = {
  /** Local id for React keys; the saved key (if any) is in `key`. */
  uid: number;
  key?: string;
  label: string;
  type: DataColumnType;
  required: boolean;
  /** Options of a "select" column, one per line. */
  options: string;
};

let nextUid = 1;

const QUANTITY_LABELS = /քանակ|հատ|qty|quantity|pcs|count|кол-?во|количество|шт/iu;

/** Which columns say when a row happened and what it's worth (uids of drafts). */
type Metrics = { date: number | null; amount: number | null; quantity: number | null };

const fits = {
  date: (c: ColumnDraft) => c.type === "date" || c.type === "datetime",
  amount: (c: ColumnDraft) => c.type === "money" || c.type === "number",
  quantity: (c: ColumnDraft) => c.type === "number",
};

/** The same guess the API makes for a table nobody chose for. */
function guessMetrics(columns: ColumnDraft[]): Metrics {
  const amount = columns.find((c) => c.type === "money")?.uid ?? null;
  return {
    date: (columns.find((c) => c.type === "datetime") ?? columns.find((c) => c.type === "date"))?.uid ?? null,
    amount,
    quantity: amount ? (columns.find((c) => c.type === "number" && QUANTITY_LABELS.test(c.label))?.uid ?? null) : null,
  };
}

function toDraft(column?: AdminDataColumnDto): ColumnDraft {
  return {
    uid: nextUid++,
    key: column?.key,
    label: column?.label ?? "",
    type: column?.type ?? "text",
    required: column?.required ?? false,
    options: (column?.options ?? []).join("\n"),
  };
}

/** A starting point per section, so a new table isn't a blank page. */
const STARTER_COLUMNS: Record<DataTableSection, AdminDataColumnDto[]> = {
  orders: [
    { label: "Հաճախորդ", type: "text", required: true, options: [] },
    { label: "Հեռախոս", type: "text", required: false, options: [] },
    { label: "Ծառայություն", type: "text", required: false, options: [] },
    { label: "Ամսաթիվ և ժամ", type: "datetime", required: false, options: [] },
    { label: "Գումար", type: "money", required: false, options: [] },
  ],
  sales: [
    { label: "Ապրանք", type: "text", required: true, options: [] },
    { label: "Քանակ", type: "number", required: false, options: [] },
    { label: "Գին", type: "money", required: false, options: [] },
    { label: "Ամսաթիվ", type: "date", required: false, options: [] },
    { label: "Վճարման եղանակ", type: "select", required: false, options: ["Կանխիկ", "Քարտ", "Փոխանցում"] },
  ],
};

/** Create a table (no `table`) or change its name and columns. */
export function TableDesigner({
  section,
  table,
  initial,
  afterCreate,
}: {
  section: DataTableSection;
  table?: AdminDataTableDto;
  /** A new table's starting name and columns (e.g. read from a file). */
  initial?: { name: string; columns: AdminDataColumnDto[] };
  /** Where to go once a new table is created (default: the table). */
  afterCreate?: (saved: AdminDataTableDto) => string;
}) {
  const router = useRouter();
  const [name, setName] = useState(table?.name ?? initial?.name ?? "");
  const [description, setDescription] = useState(table?.description ?? "");
  const [columns, setColumns] = useState<ColumnDraft[]>(() =>
    (table?.columns ?? initial?.columns ?? STARTER_COLUMNS[section]).map(toDraft),
  );
  const [metrics, setMetrics] = useState<Metrics>(() => {
    if (!table) return guessMetrics(columns);
    const uid = (key: string | null) => columns.find((c) => key !== null && c.key === key)?.uid ?? null;
    return { date: uid(table.dateColumn), amount: uid(table.amountColumn), quantity: uid(table.quantityColumn) };
  });
  // A choice that no longer fits (column removed or retyped) reads as none.
  const chosen = (field: keyof Metrics) => columns.find((c) => c.uid === metrics[field] && fits[field](c)) ?? null;
  const [pending, setPending] = useState(false);
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [failed, setFailed] = useState(false);

  function update(uid: number, patch: Partial<ColumnDraft>) {
    setColumns((cols) => cols.map((c) => (c.uid === uid ? { ...c, ...patch } : c)));
  }

  function move(index: number, delta: number) {
    setColumns((cols) => {
      const next = [...cols];
      const [moved] = next.splice(index, 1);
      next.splice(index + delta, 0, moved);
      return next;
    });
  }

  async function save() {
    setPending(true);
    setErrors({});
    setFailed(false);

    try {
      const payload = {
        ...(table ? {} : { section }),
        name,
        description: description || null,
        columns: columns.map((c) => ({
          key: c.key,
          label: c.label,
          type: c.type,
          required: c.required,
          options: c.type === "select" ? c.options.split("\n").map((o) => o.trim()).filter(Boolean) : [],
        })),
        // A column not saved yet has no key: the API finds it by its label.
        ...Object.fromEntries(
          (["date", "amount", "quantity"] as const).map((field) => {
            const column = chosen(field);
            return [`${field}_column`, column ? (column.key ?? column.label) : null];
          }),
        ),
      };

      const response = await fetch(table ? `/api/admin/data-tables/${table.id}` : "/api/admin/data-tables", {
        method: table ? "PATCH" : "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(payload),
      });
      const json = await response.json().catch(() => null);

      if (response.status === 422) {
        setErrors(validationErrors(json));
        return;
      }
      if (!response.ok) {
        setFailed(true);
        return;
      }

      const saved = (json as { data: AdminDataTableDto }).data;
      router.push(!table && afterCreate ? afterCreate(saved) : tablePath(section, saved.id));
      router.refresh();
    } catch {
      setFailed(true);
    } finally {
      setPending(false);
    }
  }

  async function remove() {
    if (!table) return;
    if (!confirm(`Ջնջե՞լ «${table.name}» աղյուսակը և նրա բոլոր ${table.rowsCount ?? 0} տողերը։ Սա հնարավոր չէ հետ բերել։`)) return;

    setPending(true);
    const response = await fetch(`/api/admin/data-tables/${table.id}`, { method: "DELETE" });
    setPending(false);

    if (!response.ok) {
      setFailed(true);
      return;
    }
    router.push(tablesPath(section));
    router.refresh();
  }

  return (
    <div className="space-y-6">
      <Card className="space-y-4">
        <div className="grid gap-4 sm:grid-cols-2">
          <label className="space-y-1.5">
            <FieldLabel>Աղյուսակի անունը</FieldLabel>
            <Input
              value={name}
              onChange={(e) => setName(e.target.value)}
              placeholder={section === "sales" ? "օր.՝ Instagram վաճառքներ" : "օր.՝ Պատվերներ 2026"}
            />
            {errors.name ? <p className="text-xs text-danger">{errors.name}</p> : null}
          </label>
          <label className="space-y-1.5">
            <FieldLabel>Նկարագրություն (ըստ ցանկության)</FieldLabel>
            <Input value={description} onChange={(e) => setDescription(e.target.value)} />
          </label>
        </div>
      </Card>

      <div className="space-y-3">
        <div>
          <h2 className="text-base font-semibold text-ink">Սյունակներ</h2>
          <p className="mt-1 max-w-2xl text-sm text-ink-muted">
            Ավելացրեք այն դաշտերը, որոնք կան ձեր բիզնեսում։ Անունը կարելի է փոխել հետո՝ տվյալները չեն կորչի։ Ֆայլից
            ներմուծելիս ֆայլի սյունակները կհամընկնեն այս անուններով։
          </p>
        </div>
        {errors.columns ? <p className="text-sm text-danger">{errors.columns}</p> : null}

        {columns.map((column, index) => (
          <Card key={column.uid} className="space-y-3 p-4">
            <div className="grid gap-3 sm:grid-cols-[1fr_220px_auto] sm:items-end">
              <label className="space-y-1.5">
                <FieldLabel>Անուն</FieldLabel>
                <Input value={column.label} onChange={(e) => update(column.uid, { label: e.target.value })} />
              </label>
              <label className="space-y-1.5">
                <FieldLabel>Տեսակ</FieldLabel>
                <Select
                  value={column.type}
                  onChange={(e) => update(column.uid, { type: e.target.value as DataColumnType })}
                >
                  {COLUMN_TYPES.map((type) => (
                    <option key={type} value={type}>
                      {COLUMN_TYPE_LABELS[type]}
                    </option>
                  ))}
                </Select>
              </label>
              <div className="flex items-center gap-1">
                <label className="mr-2 flex h-10 items-center gap-2 text-sm text-ink-muted">
                  <input
                    type="checkbox"
                    checked={column.required}
                    onChange={(e) => update(column.uid, { required: e.target.checked })}
                  />
                  Պարտադիր
                </label>
                <Button variant="ghost" size="sm" aria-label="Վեր" disabled={index === 0} onClick={() => move(index, -1)}>
                  <ArrowUp className="h-4 w-4" />
                </Button>
                <Button
                  variant="ghost"
                  size="sm"
                  aria-label="Վար"
                  disabled={index === columns.length - 1}
                  onClick={() => move(index, 1)}
                >
                  <ArrowDown className="h-4 w-4" />
                </Button>
                <Button
                  variant="ghost"
                  size="sm"
                  aria-label="Հեռացնել սյունակը"
                  disabled={columns.length === 1}
                  onClick={() => setColumns((cols) => cols.filter((c) => c.uid !== column.uid))}
                >
                  <Trash2 className="h-4 w-4" />
                </Button>
              </div>
            </div>

            {column.type === "select" ? (
              <label className="block space-y-1.5">
                <FieldLabel>Տարբերակներ (յուրաքանչյուրը նոր տողում)</FieldLabel>
                <Textarea
                  rows={3}
                  value={column.options}
                  onChange={(e) => update(column.uid, { options: e.target.value })}
                />
              </label>
            ) : null}

            {[errors[`columns.${index}.label`], errors[`columns.${index}.type`], errors[`columns.${index}.options`]]
              .filter(Boolean)
              .map((message) => (
                <p key={message} className="text-xs text-danger">
                  {message}
                </p>
              ))}
          </Card>
        ))}

        <Button variant="secondary" onClick={() => setColumns((cols) => [...cols, toDraft()])}>
          + Ավելացնել սյունակ
        </Button>
      </div>

      <Card className="space-y-4">
        <div>
          <h2 className="text-base font-semibold text-ink">Վերլուծության համար</h2>
          <p className="mt-1 max-w-2xl text-sm text-ink-muted">
            Ըստ այս սյունակների են հաշվվում օրերի և ամիսների թվերը վերլուծությունում և AI օգնականում։ Ամսաթիվը պարտադիր է
            յուրաքանչյուր տողում։
          </p>
        </div>
        <div className="grid gap-4 sm:grid-cols-3">
          {(
            [
              ["date", section === "orders" ? "Պատվերի ամսաթիվը" : "Վաճառքի ամսաթիվը", "Ամսաթիվ կամ Ամսաթիվ և ժամ տեսակի"],
              ["amount", "Գումարը", "Գումար կամ Թիվ տեսակի"],
              ["quantity", "Քանակը (ըստ ցանկության)", "Գումարը կբազմապատկվի քանակով"],
            ] as const
          ).map(([field, label, hint]) => (
            <label key={field} className="space-y-1.5">
              <FieldLabel>{label}</FieldLabel>
              <Select
                value={chosen(field)?.uid ?? ""}
                onChange={(e) => setMetrics((m) => ({ ...m, [field]: e.target.value === "" ? null : Number(e.target.value) }))}
              >
                <option value="">—</option>
                {columns
                  .filter((c) => fits[field](c) && c.label.trim() !== "" && (field !== "quantity" || c.uid !== chosen("amount")?.uid))
                  .map((c) => (
                    <option key={c.uid} value={c.uid}>
                      {c.label}
                    </option>
                  ))}
              </Select>
              <p className="text-xs text-ink-faint">{hint}</p>
            </label>
          ))}
        </div>
        {!chosen("date") ? (
          <p className="text-sm text-warning">
            Առանց ամսաթվի սյունակի տողերը չեն երևա օրերի և ամիսների վերլուծությունում։ Ավելացրեք «Ամսաթիվ» կամ «Ամսաթիվ և
            ժամ» սյունակ։
          </p>
        ) : null}
      </Card>

      <div className="flex flex-wrap items-center gap-3">
        <Button onClick={save} disabled={pending}>
          {table ? "Պահպանել" : "Ստեղծել աղյուսակը"}
        </Button>
        {failed ? <span className="text-sm text-danger">Չհաջողվեց պահպանել։</span> : null}
        {Object.keys(errors).length > 0 ? <span className="text-sm text-danger">Ստուգեք դաշտերը։</span> : null}
        {table ? (
          <Button variant="secondary" className="ml-auto" onClick={remove} disabled={pending}>
            Ջնջել աղյուսակը
          </Button>
        ) : null}
      </div>
    </div>
  );
}
