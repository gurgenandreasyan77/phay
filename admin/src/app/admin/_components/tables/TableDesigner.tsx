"use client";

import { useState } from "react";
import { useRouter } from "next/navigation";
import { ArrowDown, ArrowUp, Sparkles, Trash2 } from "lucide-react";
import type {
  AdminDataColumnDto,
  AdminDataTableDto,
  ColumnRole,
  DataColumnType,
  DataTableSection,
  TablePeriod,
} from "@/lib/adminApi";
import { Button, Card, FieldLabel, Input, Select, Textarea } from "../ui";
import {
  COLUMN_ROLES,
  COLUMN_TYPE_LABELS,
  COLUMN_TYPES,
  formatMoment,
  ROLE_GROUPS,
  ROLE_ORDER,
  roleLabels,
  tablePath,
  tablesPath,
  validationErrors,
} from "./shared";
import { PERIOD_KINDS, periodOf, TABLE_PERIOD_LABELS, today } from "./periods";

type ColumnDraft = {
  /** Local id for React keys; the saved key (if any) is in `key`. */
  uid: number;
  key?: string;
  label: string;
  type: DataColumnType;
  required: boolean;
  /** Options of a "select" column, one per line. */
  options: string;
  /** What the column means for analytics; null for the business's own column. */
  role: ColumnRole | null;
};

let nextUid = 1;

function toDraft(column?: AdminDataColumnDto): ColumnDraft {
  return {
    uid: nextUid++,
    key: column?.key,
    label: column?.label ?? "",
    type: column?.type ?? "text",
    required: column?.required ?? false,
    options: (column?.options ?? []).join("\n"),
    role: column?.role ?? null,
  };
}

/** A new column of a ready-made kind: its Armenian name, type and options. */
function roleDraft(role: ColumnRole, section: DataTableSection): ColumnDraft {
  const info = COLUMN_ROLES[role];
  return toDraft({
    label: roleLabels(role, section).hy,
    type: info.types[0],
    required: false,
    options: info.options ?? [],
    role,
  });
}

/** A starting point per section, so a new table isn't a blank page. */
const STARTER_ROLES: Record<DataTableSection, ColumnRole[]> = {
  orders: ["customer", "phone", "item", "date", "amount", "cost", "performer"],
  sales: ["item", "quantity", "unit_price", "date", "payment"],
  expenses: ["date", "category", "item", "amount", "supplier", "payment", "note"],
};

function starterColumns(section: DataTableSection): ColumnDraft[] {
  return STARTER_ROLES[section].map((role) => {
    const draft = roleDraft(role, section);
    if (role === "customer" || (section === "sales" && role === "item")) draft.required = true;
    if (section !== "orders" && role === "date") draft.type = "date";
    if (section === "expenses" && (role === "category" || role === "amount")) draft.required = true;
    return draft;
  });
}

/** A name the user didn't type: empty or one of the ready-made names. */
function isStockLabel(label: string, section: DataTableSection): boolean {
  const value = label.trim().toLowerCase();
  return (
    value === "" ||
    ROLE_ORDER.some((role) => Object.values(roleLabels(role, section)).some((l) => l.toLowerCase() === value))
  );
}

/** How a row's money is counted, as the API does: the total, else price × quantity − discount + delivery. */
function amountRule(columns: ColumnDraft[]): string | null {
  const byRole = (role: ColumnRole) => columns.find((c) => c.role === role && c.label.trim() !== "");
  const total = byRole("amount");
  const price = byRole("unit_price");
  const quantity = byRole("quantity");
  const off = [byRole("discount"), byRole("discount_percent")].filter(Boolean).map((c) => ` − «${c!.label}»`).join("");
  const delivery = byRole("delivery_fee");
  const fromPrice = price
    ? `«${price.label}» × «${quantity?.label ?? "1"}»${off}${delivery ? ` + «${delivery.label}»` : ""}`
    : null;
  if (total && fromPrice) return `«${total.label}», իսկ եթե դատարկ է՝ ${fromPrice}`;
  if (total) return `«${total.label}»`;
  return fromPrice;
}

/** The ready-made meanings as a list in groups, each with its three names. */
function RoleOptions({ roles, section, disabled }: { roles: ColumnRole[]; section: DataTableSection; disabled?: (role: ColumnRole) => boolean }) {
  return (
    <>
      {ROLE_GROUPS.map((group) => {
        const inGroup = roles.filter((role) => COLUMN_ROLES[role].group === group.id);
        if (inGroup.length === 0) return null;
        return (
          <optgroup key={group.id} label={group.label}>
            {inGroup.map((role) => {
              const labels = roleLabels(role, section);
              return (
                <option key={role} value={role} disabled={disabled?.(role)}>
                  {labels.hy} · {labels.ru} · {labels.en}
                </option>
              );
            })}
          </optgroup>
        );
      })}
    </>
  );
}

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
  // A new table by hand is a month's; one from a file may cover any dates.
  // "" is a table made before periods, left as it is until one is chosen.
  const [period, setPeriod] = useState<TablePeriod | "">(() =>
    table ? (table.period ?? "") : initial ? "custom" : "month",
  );
  const [periodDate, setPeriodDate] = useState(table?.periodInfo?.start ?? today());
  const [columns, setColumns] = useState<ColumnDraft[]>(() => {
    const given = table?.columns ?? initial?.columns;
    return given ? given.map(toDraft) : starterColumns(section);
  });
  const [pending, setPending] = useState(false);
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [failed, setFailed] = useState(false);
  // What "Recognise" found: how many columns got a meaning, or why not.
  const [guessed, setGuessed] = useState<string | null>(null);

  function update(uid: number, patch: Partial<ColumnDraft>) {
    setColumns((cols) => cols.map((c) => (c.uid === uid ? { ...c, ...patch } : c)));
  }

  /** A column's meaning changed: its type, name and options follow unless the user set their own. */
  function setRole(column: ColumnDraft, role: ColumnRole | null) {
    if (role === null) {
      update(column.uid, { role });
      return;
    }
    const info = COLUMN_ROLES[role];
    update(column.uid, {
      role,
      type: info.types.includes(column.type) ? column.type : info.types[0],
      label: isStockLabel(column.label, section) ? roleLabels(role, section).hy : column.label,
      options: column.options.trim() === "" ? (info.options ?? []).join("\n") : column.options,
    });
  }

  /**
   * Meanings for the columns that have none, read by the API from their
   * names and the values already in the table ("iPhone 15" → model).
   * Shown in the form; nothing is saved until "Save".
   */
  async function recognise() {
    if (!table) return;
    setGuessed(null);
    const response = await fetch(`/api/admin/data-tables/${table.id}/role-suggestions`).catch(() => null);
    const json = response?.ok ? await response.json().catch(() => null) : null;
    if (!json) {
      setGuessed("Չհաջողվեց ստուգել։ Փորձեք կրկին։");
      return;
    }
    const found = (json.data as { key: string; role: ColumnRole }[]).filter(
      (s) => !columns.some((c) => c.role === s.role),
    );
    for (const suggestion of found) {
      const column = columns.find((c) => c.key === suggestion.key && c.role === null);
      if (column) setRole(column, suggestion.role);
    }
    setGuessed(
      found.length > 0
        ? `${found.length} սյունակի իմաստը ճանաչվեց։ Ստուգեք և սեղմեք «Պահպանել»։`
        : "Նոր իմաստներ չգտնվեցին․ ընտրեք ձեռքով «Ինչ է այս սյունակը» ցանկից։",
    );
  }

  const usedRoles = new Set(columns.map((c) => c.role).filter((r): r is ColumnRole => r !== null));
  const freeRoles = ROLE_ORDER.filter((role) => !usedRoles.has(role));
  const rule = amountRule(columns);
  const hasDate = columns.some((c) => c.role === "date");

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
        ...(period === ""
          ? {}
          : { period, periodDate: period === "custom" ? null : periodDate || null }),
        columns: columns.map((c) => ({
          key: c.key,
          label: c.label,
          type: c.type,
          required: c.required,
          options: c.type === "select" ? c.options.split("\n").map((o) => o.trim()).filter(Boolean) : [],
          role: c.role,
        })),
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
              placeholder={section === "sales" ? "օր.՝ Instagram վաճառքներ" : section === "expenses" ? "օր.՝ Ծախսեր 2026" : "օր.՝ Պատվերներ 2026"}
            />
            {errors.name ? <p className="text-xs text-danger">{errors.name}</p> : null}
          </label>
          <label className="space-y-1.5">
            <FieldLabel>Նկարագրություն (ըստ ցանկության)</FieldLabel>
            <Input value={description} onChange={(e) => setDescription(e.target.value)} />
          </label>
        </div>
        <div className="grid gap-4 sm:grid-cols-2">
          <label className="space-y-1.5">
            <FieldLabel>Ժամանակահատված</FieldLabel>
            <Select value={period} onChange={(e) => setPeriod(e.target.value as TablePeriod | "")}>
              {table && table.period === null ? (
                <option value="">
                  {table.periodInfo ? `Ավտոմատ՝ ${table.periodInfo.label} (ըստ անվան կամ տողերի)` : "Ընտրված չէ (ցանկացած ամսաթիվ)"}
                </option>
              ) : null}
              {[...PERIOD_KINDS, "custom" as const].map((kind) => (
                <option key={kind} value={kind}>
                  {TABLE_PERIOD_LABELS[kind]}
                </option>
              ))}
            </Select>
            {errors.period ? <p className="text-xs text-danger">{errors.period}</p> : null}
          </label>
          {period !== "" && period !== "custom" ? (
            <label className="space-y-1.5">
              <FieldLabel>Ո՞ր ժամանակահատվածի համար</FieldLabel>
              <Input type="date" value={periodDate} onChange={(e) => setPeriodDate(e.target.value)} />
              {/^\d{4}-\d{2}-\d{2}$/.test(periodDate) ? (
                <p className="text-xs text-ink-muted">
                  {(() => {
                    const p = periodOf(period, periodDate);
                    return p.start === p.end
                      ? p.label
                      : `${p.label} · ${formatMoment(p.start)} – ${formatMoment(p.end)}`;
                  })()}
                </p>
              ) : null}
              {errors.periodDate ? <p className="text-xs text-danger">{errors.periodDate}</p> : null}
            </label>
          ) : null}
        </div>
        <p className="text-sm text-ink-muted">
          {period === "custom" || period === ""
            ? "Աղյուսակը ընդունում է ցանկացած ամսաթվի պատվեր։"
            : "Օրացույցից ավելացված պատվերը, որի ամսաթիվն այլ ժամանակահատվածից է, կգնա այդ ժամանակահատվածի աղյուսակ (կստեղծվի ինքնաշխատ՝ նույն սյունակներով)։"}{" "}
          Յուրաքանչյուր տող միշտ գրանցվում է իր տարում, եռամսյակում, ամսում, շաբաթում և օրում։
        </p>
      </Card>

      <div className="space-y-3">
        <div>
          <h2 className="text-base font-semibold text-ink">Սյունակներ</h2>
          <p className="mt-1 max-w-2xl text-sm text-ink-muted">
            Ավելացրեք այն դաշտերը, որոնք կան ձեր բիզնեսում։ «Ինչ է այս սյունակը» ընտրությամբ վերլուծությունը և AI-ը
            հասկանում են սյունակի իմաստը՝ ինչ անուն էլ որ տաք (օր.՝ «Գին», «Цена» կամ «gin»)։ Անունը կարելի է փոխել
            հետո՝ տվյալները չեն կորչի։
          </p>
        </div>
        {table && columns.some((c) => c.role === null && c.key) ? (
          <div className="flex flex-wrap items-center gap-3 rounded-md border border-primary/30 bg-primary-light/40 px-4 py-3">
            <p className="flex-1 text-sm text-ink">
              {columns.filter((c) => c.role === null).length} սյունակի իմաստը նշված չէ, ուստի վիճակագրությունը և AI-ը
              դրանք չեն հասկանում։ Servio-ն կարող է ճանաչել դրանք ըստ անվան և արժեքների (օր.՝ «iPhone 15» → Մոդել)։
            </p>
            <Button variant="secondary" size="sm" onClick={recognise}>
              <Sparkles className="h-4 w-4" />
              Ճանաչել ինքնաշխատ
            </Button>
            {guessed ? <p className="w-full text-sm text-ink-muted">{guessed}</p> : null}
          </div>
        ) : null}
        {errors.columns ? <p className="text-sm text-danger">{errors.columns}</p> : null}

        {columns.map((column, index) => (
          <Card key={column.uid} className="space-y-3 p-4">
            <div className="grid gap-3 sm:grid-cols-[220px_1fr_200px_auto] sm:items-end">
              <label className="space-y-1.5">
                <FieldLabel>Ինչ է այս սյունակը</FieldLabel>
                <Select
                  value={column.role ?? ""}
                  onChange={(e) => setRole(column, e.target.value === "" ? null : (e.target.value as ColumnRole))}
                >
                  <option value="">Իմ սյունակը</option>
                  <RoleOptions
                    roles={ROLE_ORDER}
                    section={section}
                    disabled={(role) => role !== column.role && usedRoles.has(role)}
                  />
                </Select>
              </label>
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
                  {(column.role ? COLUMN_ROLES[column.role].types : COLUMN_TYPES).map((type) => (
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

            {column.role ? <p className="text-xs text-ink-faint">{COLUMN_ROLES[column.role].hint}</p> : null}

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

            {[
              errors[`columns.${index}.role`],
              errors[`columns.${index}.label`],
              errors[`columns.${index}.type`],
              errors[`columns.${index}.options`],
            ]
              .filter(Boolean)
              .map((message) => (
                <p key={message} className="text-xs text-danger">
                  {message}
                </p>
              ))}
          </Card>
        ))}

        <div className="flex flex-wrap items-center gap-3">
          {freeRoles.length > 0 ? (
            <Select
              className="w-72"
              value=""
              aria-label="Ավելացնել պատրաստի սյունակ"
              onChange={(e) => {
                const role = e.target.value as ColumnRole;
                if (role) setColumns((cols) => [...cols, roleDraft(role, section)]);
              }}
            >
              <option value="">+ Ավելացնել պատրաստի սյունակ…</option>
              <RoleOptions roles={freeRoles} section={section} />
            </Select>
          ) : null}
          <Button variant="secondary" onClick={() => setColumns((cols) => [...cols, toDraft()])}>
            + Իմ սյունակը
          </Button>
        </div>
      </div>

      <Card className="space-y-2">
        <h2 className="text-base font-semibold text-ink">Ինչպես է հաշվվում վերլուծությունը</h2>
        <p className="text-sm text-ink-muted">
          Տողի գումարը՝ {rule ?? "չի հաշվվում․ ընտրեք «Գումար» կամ «Գին» սյունակ"}։ Քանակը կարող է լինել կոտորակային
          (0,5 կգ × 3000 ֏ = 1500 ֏), իսկ քանակները գումարվում են ըստ չափման միավորի։
        </p>
        {!hasDate ? (
          <p className="text-sm text-warning">
            Առանց «Ամսաթիվ» սյունակի տողերը չեն երևա օրերի և ամիսների վերլուծությունում։
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
