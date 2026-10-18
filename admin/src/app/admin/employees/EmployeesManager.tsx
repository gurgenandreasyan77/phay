"use client";

import { useState } from "react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { Pencil, Trash2, UserPlus } from "lucide-react";
import type { AdminEmployeeDto } from "@/lib/adminApi";
import { Badge, Button, Card, EmptyRow, FieldLabel, Input, TableWrap, Td, Th, Thead, Tr } from "../_components/ui";
import { validationErrors } from "../_components/tables/shared";

type Draft = { name: string; position: string; phone: string };

const EMPTY: Draft = { name: "", position: "", phone: "" };

async function send(url: string, method: string, body?: unknown): Promise<{ ok: boolean; errors: Record<string, string> }> {
  const response = await fetch(url, {
    method,
    headers: { "Content-Type": "application/json" },
    body: body === undefined ? undefined : JSON.stringify(body),
  }).catch(() => null);
  if (!response) return { ok: false, errors: {} };
  if (response.status === 422) return { ok: false, errors: validationErrors(await response.json().catch(() => null)) };
  return { ok: response.ok, errors: {} };
}

function payload(draft: Draft) {
  return { name: draft.name, position: draft.position || null, phone: draft.phone || null };
}

/** Adding an employee: always at the top of the page. */
export function NewEmployeeForm() {
  const router = useRouter();
  const [draft, setDraft] = useState<Draft>(EMPTY);
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [status, setStatus] = useState<"idle" | "saving" | "saved" | "error">("idle");

  async function save() {
    setStatus("saving");
    const result = await send("/api/admin/employees", "POST", payload(draft));
    setErrors(result.errors);
    if (!result.ok) {
      setStatus(Object.keys(result.errors).length > 0 ? "idle" : "error");
      return;
    }
    setDraft(EMPTY);
    setStatus("saved");
    router.refresh();
  }

  const field = (key: keyof Draft, label: string, placeholder: string) => (
    <label className="space-y-1.5">
      <FieldLabel>{label}</FieldLabel>
      <Input value={draft[key]} placeholder={placeholder} onChange={(e) => setDraft((d) => ({ ...d, [key]: e.target.value }))} />
      {errors[key] ? <p className="text-xs text-danger">{errors[key]}</p> : null}
    </label>
  );

  return (
    <Card>
      <form
        className="space-y-4"
        onSubmit={(e) => {
          e.preventDefault();
          save();
        }}
      >
        <h2 className="text-base font-semibold text-ink">Նոր աշխատակից</h2>
        <div className="grid gap-4 sm:grid-cols-3">
          {field("name", "Անուն *", "օր.՝ Աննա")}
          {field("position", "Պաշտոն", "օր.՝ վարպետ")}
          {field("phone", "Հեռախոս", "+374…")}
        </div>
        <div className="flex items-center gap-3">
          <Button type="submit" disabled={status === "saving"}>
            <UserPlus className="h-4 w-4" />
            Ավելացնել աշխատակից
          </Button>
          {status === "saved" ? <span className="text-sm text-success">Ավելացված է։</span> : null}
          {status === "error" ? <span className="text-sm text-danger">Չհաջողվեց պահպանել։</span> : null}
        </div>
      </form>
    </Card>
  );
}

/** The employees: open one to see their orders; rename, mark as no longer working, delete. */
export function EmployeesList({ employees }: { employees: AdminEmployeeDto[] }) {
  const router = useRouter();
  const [editing, setEditing] = useState<number | null>(null);

  async function remove(employee: AdminEmployeeDto) {
    const rows = employee.ordersCount ?? 0;
    const note =
      rows > 0
        ? ` Նրա ${rows} պատվերները կմնան աղյուսակներում իր անունով, բայց այլևս չեն հաշվվի նրա վրա։ Եթե նա պարզապես այլևս չի աշխատում, ավելի լավ է նշել «Չի աշխատում»։`
        : "";
    if (!confirm(`Ջնջե՞լ «${employee.name}» աշխատակցին։${note}`)) return;
    if ((await send(`/api/admin/employees/${employee.id}`, "DELETE")).ok) router.refresh();
  }

  async function toggleActive(employee: AdminEmployeeDto) {
    if ((await send(`/api/admin/employees/${employee.id}`, "PATCH", { isActive: !employee.isActive })).ok) router.refresh();
  }

  return (
    <TableWrap>
      <Thead>
        <Th>Անուն</Th>
        <Th>Պաշտոն</Th>
        <Th>Հեռախոս</Th>
        <Th className="text-right">Պատվերներ</Th>
        <Th className="w-56" />
      </Thead>
      <tbody>
        {employees.map((employee) =>
          editing === employee.id ? (
            <EditRow
              key={employee.id}
              employee={employee}
              onDone={(saved) => {
                setEditing(null);
                if (saved) router.refresh();
              }}
            />
          ) : (
            <Tr key={employee.id}>
              <Td>
                <Link href={`/admin/employees/${employee.id}`} className="font-medium text-ink hover:text-primary">
                  {employee.name}
                </Link>
                {!employee.isActive ? (
                  <Badge className="ml-2" tone="default">
                    Չի աշխատում
                  </Badge>
                ) : null}
              </Td>
              <Td className="text-ink-muted">{employee.position ?? "—"}</Td>
              <Td className="text-ink-muted">{employee.phone ?? "—"}</Td>
              <Td className="text-right tabular-nums">
                <Link href={`/admin/employees/${employee.id}`} className="hover:text-primary">
                  {employee.ordersCount ?? 0}
                </Link>
              </Td>
              <Td className="whitespace-nowrap text-right">
                <Button variant="ghost" size="sm" onClick={() => toggleActive(employee)}>
                  {employee.isActive ? "Չի աշխատում" : "Աշխատում է"}
                </Button>
                <Button variant="ghost" size="sm" aria-label="Խմբագրել" onClick={() => setEditing(employee.id)}>
                  <Pencil className="h-4 w-4" />
                </Button>
                <Button variant="ghost" size="sm" aria-label="Ջնջել" onClick={() => remove(employee)}>
                  <Trash2 className="h-4 w-4" />
                </Button>
              </Td>
            </Tr>
          ),
        )}
        {employees.length === 0 ? <EmptyRow colSpan={5}>Աշխատակիցներ դեռ չկան։ Ավելացրեք առաջինը վերևում։</EmptyRow> : null}
      </tbody>
    </TableWrap>
  );
}

function EditRow({ employee, onDone }: { employee: AdminEmployeeDto; onDone: (saved: boolean) => void }) {
  const [draft, setDraft] = useState<Draft>({ name: employee.name, position: employee.position ?? "", phone: employee.phone ?? "" });
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [pending, setPending] = useState(false);

  async function save() {
    setPending(true);
    const result = await send(`/api/admin/employees/${employee.id}`, "PATCH", payload(draft));
    setPending(false);
    setErrors(result.errors);
    if (result.ok) onDone(true);
  }

  const field = (key: keyof Draft) => (
    <td className="px-2 py-2 align-top">
      <Input
        value={draft[key]}
        onChange={(e) => setDraft((d) => ({ ...d, [key]: e.target.value }))}
        onKeyDown={(e) => {
          if (e.key === "Enter") save();
        }}
      />
      {errors[key] ? <p className="mt-1 text-xs text-danger">{errors[key]}</p> : null}
    </td>
  );

  return (
    <tr className="border-b border-border bg-primary-light/40 last:border-0">
      {field("name")}
      {field("position")}
      {field("phone")}
      <td />
      <td className="whitespace-nowrap px-2 py-2 text-right align-top">
        <Button size="sm" onClick={save} disabled={pending}>
          Պահպանել
        </Button>
        <Button size="sm" variant="ghost" onClick={() => onDone(false)} disabled={pending}>
          Չեղարկել
        </Button>
      </td>
    </tr>
  );
}
