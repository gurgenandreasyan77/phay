"use client";

import { useState } from "react";
import { useRouter } from "next/navigation";
import { Pencil, Plus, Trash2 } from "lucide-react";
import type { ExpenseCategoryDto, RecurringExpenseDto, RecurringFrequency } from "@/lib/adminApi";
import { Button, Card, EmptyRow, FieldLabel, Input, Select, TableWrap, Td, Th, Thead, Tr } from "@/ui";
import { formatMoment, formatMoney } from "../_components/tables/shared";
import { today } from "../_components/tables/periods";
import { FREQUENCIES, FREQUENCY_LABELS, send } from "./shared";

type Draft = { name: string; categoryId: string; amount: string; frequency: RecurringFrequency; startsOn: string; endsOn: string; note: string };

function draftOf(expense?: RecurringExpenseDto): Draft {
  return {
    name: expense?.name ?? "",
    categoryId: expense?.category ? String(expense.category.id) : "",
    amount: expense ? String(expense.amount) : "",
    frequency: expense?.frequency ?? "month",
    startsOn: expense?.startsOn ?? today(),
    endsOn: expense?.endsOn ?? "",
    note: expense?.note ?? "",
  };
}

function payload(d: Draft) {
  return {
    name: d.name,
    categoryId: d.categoryId ? Number(d.categoryId) : null,
    amount: d.amount.replace(/\s/g, "").replace(",", "."),
    frequency: d.frequency,
    startsOn: d.startsOn,
    endsOn: d.endsOn || null,
    note: d.note || null,
  };
}

/** Adding or changing an expense that repeats. */
function RecurringForm({
  categories,
  expense,
  onDone,
}: {
  categories: ExpenseCategoryDto[];
  expense?: RecurringExpenseDto;
  onDone: () => void;
}) {
  const router = useRouter();
  const [draft, setDraft] = useState<Draft>(draftOf(expense));
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [status, setStatus] = useState<"idle" | "saving" | "error">("idle");
  const set = (patch: Partial<Draft>) => setDraft((d) => ({ ...d, ...patch }));

  async function save() {
    setStatus("saving");
    const result = await send(expense ? `/api/admin/recurring-expenses/${expense.id}` : "/api/admin/recurring-expenses", expense ? "PATCH" : "POST", payload(draft));
    setErrors(result.errors);
    if (!result.ok) {
      setStatus(Object.keys(result.errors).length > 0 ? "idle" : "error");
      return;
    }
    setStatus("idle");
    if (!expense) setDraft(draftOf());
    router.refresh();
    onDone();
  }

  const error = (key: string) => (errors[key] ? <p className="text-xs text-danger">{errors[key]}</p> : null);

  return (
    <form
      className="space-y-4"
      onSubmit={(e) => {
        e.preventDefault();
        save();
      }}
    >
      <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <label className="space-y-1.5">
          <FieldLabel>Անվանում *</FieldLabel>
          <Input value={draft.name} onChange={(e) => set({ name: e.target.value })} placeholder="օր.՝ Տարածքի վարձ" />
          {error("name")}
        </label>
        <label className="space-y-1.5">
          <FieldLabel>Ծախսի տեսակ *</FieldLabel>
          <Select value={draft.categoryId} onChange={(e) => set({ categoryId: e.target.value })}>
            <option value="">— Ընտրեք —</option>
            {categories.map((c) => (
              <option key={c.id} value={c.id}>
                {c.inProfit ? c.name : `${c.name} (շահույթից դուրս)`}
              </option>
            ))}
          </Select>
          {error("categoryId")}
        </label>
        <label className="space-y-1.5">
          <FieldLabel>Գումար (֏) *</FieldLabel>
          <Input inputMode="decimal" className="tabular-nums" value={draft.amount} onChange={(e) => set({ amount: e.target.value })} placeholder="150000" />
          {error("amount")}
        </label>
        <label className="space-y-1.5">
          <FieldLabel>Որքան հաճախ</FieldLabel>
          <Select value={draft.frequency} onChange={(e) => set({ frequency: e.target.value as RecurringFrequency })}>
            {FREQUENCIES.map((f) => (
              <option key={f} value={f}>
                {FREQUENCY_LABELS[f]}
              </option>
            ))}
          </Select>
          {error("frequency")}
        </label>
        <label className="space-y-1.5">
          <FieldLabel>Առաջին վճարման օրը *</FieldLabel>
          <Input type="date" value={draft.startsOn} onChange={(e) => set({ startsOn: e.target.value })} />
          {error("startsOn")}
        </label>
        <label className="space-y-1.5">
          <FieldLabel>Մինչև (եթե ավարտվում է)</FieldLabel>
          <Input type="date" value={draft.endsOn} onChange={(e) => set({ endsOn: e.target.value })} />
          {error("endsOn")}
        </label>
      </div>
      <div className="flex flex-wrap items-center gap-3">
        <Button type="submit" disabled={status === "saving"}>
          {expense ? "Պահպանել" : (
            <>
              <Plus className="h-4 w-4" />
              Ավելացնել կրկնվող ծախս
            </>
          )}
        </Button>
        {expense ? (
          <Button type="button" variant="ghost" onClick={onDone}>
            Չեղարկել
          </Button>
        ) : null}
        {status === "error" ? <span className="text-sm text-danger">Չհաջողվեց պահպանել։</span> : null}
      </div>
    </form>
  );
}

/**
 * Expenses that repeat (rent every month, taxes every quarter): entered
 * once, counted on each due date, never typed again.
 */
export function RecurringManager({ expenses, categories }: { expenses: RecurringExpenseDto[]; categories: ExpenseCategoryDto[] }) {
  const router = useRouter();
  const [editing, setEditing] = useState<number | null>(null);
  const active = expenses.filter((e) => e.endsOn === null || e.endsOn >= today());
  const monthly = active.reduce((sum, e) => sum + e.monthly, 0);

  async function remove(expense: RecurringExpenseDto) {
    if (!confirm(`Ջնջե՞լ «${expense.name}» կրկնվող ծախսը։ Այն այլևս չի հաշվվի ոչ մի ժամանակահատվածում, նաև անցածներում։ Եթե այն պարզապես ավարտվել է, ավելի լավ է նշել «Մինչև» օրը։`)) return;
    if ((await send(`/api/admin/recurring-expenses/${expense.id}`, "DELETE")).ok) router.refresh();
  }

  return (
    <div className="space-y-4">
      <Card>
        <RecurringForm categories={categories} onDone={() => undefined} />
      </Card>
      <TableWrap>
        <Thead>
          <Th>Անվանում</Th>
          <Th>Տեսակ</Th>
          <Th className="text-right">Գումար</Th>
          <Th>Որքան հաճախ</Th>
          <Th>Ժամկետ</Th>
          <Th className="text-right">Ամսական</Th>
          <Th className="w-24" />
        </Thead>
        <tbody>
          {expenses.length === 0 ? (
            <EmptyRow colSpan={7}>Կրկնվող ծախսեր դեռ չկան, օր.՝ ամսական վարձ կամ եռամսյակային հարկ։</EmptyRow>
          ) : (
            expenses.map((expense) =>
              editing === expense.id ? (
                <tr key={expense.id} className="border-b border-border bg-primary-light/40">
                  <td colSpan={7} className="p-4">
                    <RecurringForm categories={categories} expense={expense} onDone={() => setEditing(null)} />
                  </td>
                </tr>
              ) : (
                <Tr key={expense.id}>
                  <Td className="font-medium text-ink">{expense.name}</Td>
                  <Td>
                    {expense.category ? (
                      expense.category.inProfit ? expense.category.name : <span className="text-ink-muted">{expense.category.name} (շահույթից դուրս)</span>
                    ) : (
                      "—"
                    )}
                  </Td>
                  <Td className="whitespace-nowrap text-right tabular-nums">{formatMoney(expense.amount)}</Td>
                  <Td>{FREQUENCY_LABELS[expense.frequency]}</Td>
                  <Td className="whitespace-nowrap text-ink-muted">
                    {formatMoment(expense.startsOn)}
                    {expense.endsOn ? ` – ${formatMoment(expense.endsOn)}` : " -ից"}
                  </Td>
                  <Td className="whitespace-nowrap text-right tabular-nums text-ink-muted">≈ {formatMoney(expense.monthly)}</Td>
                  <Td className="whitespace-nowrap text-right">
                    <div className="flex justify-end gap-1">
                      <Button size="sm" variant="ghost" aria-label="Փոխել" onClick={() => setEditing(expense.id)}>
                        <Pencil className="h-4 w-4" />
                      </Button>
                      <Button size="sm" variant="ghost" aria-label="Ջնջել" onClick={() => remove(expense)}>
                        <Trash2 className="h-4 w-4" />
                      </Button>
                    </div>
                  </Td>
                </Tr>
              ),
            )
          )}
        </tbody>
      </TableWrap>
      {active.length > 0 ? (
        <p className="text-sm text-ink-muted">
          Գործող կրկնվող ծախսերը միասին՝ մոտ <span className="font-medium text-ink">{formatMoney(Math.round(monthly))}</span> ամսական։
        </p>
      ) : null}
    </div>
  );
}
