"use client";

import { useState } from "react";
import { useRouter } from "next/navigation";
import { Pencil, Trash2 } from "lucide-react";
import type { AdminEmployeeDto } from "@/lib/adminApi";
import { Badge, Button, EmptyRow, Input, TableWrap, Td, Th, Thead, Tr } from "../_components/ui";
import { validationErrors } from "../_components/tables/shared";

type Draft = { name: string; position: string; phone: string };

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

/** The list of employees: add, rename, mark as no longer working, delete. */
export function EmployeesManager({ employees }: { employees: AdminEmployeeDto[] }) {
  const router = useRouter();
  const [editing, setEditing] = useState<number | null>(null);

  async function remove(employee: AdminEmployeeDto) {
    const rows = employee.ordersCount ?? 0;
    const note = rows > 0 ? ` Նրա ${rows} պատվերները կմնան աղյուսակներում իր անունով, բայց այլևս չեն հաշվվի նրա վրա։ Եթե նա պարզապես այլևս չի աշխատում, ավելի լավ է նշել «Չի աշխատում»։` : "";
    if (!confirm(`Ջնջե՞լ «${employee.name}» աշխատակցին։${note}`)) return;
    if ((await send(`/api/admin/employees/${employee.id}`, "DELETE")).ok) router.refresh();
  }

  async function toggleActive(employee: AdminEmployeeDto) {
    if ((await send(`/api/admin/employees/${employee.id}`, "PATCH", { isActive: !employee.isActive })).ok) router.refresh();
  }

  return (
    <div className="space-y-4">
      <TableWrap>
        <Thead>
          <Th>Անուն</Th>
          <Th>Պաշտոն</Th>
          <Th>Հեռախոս</Th>
          <Th className="text-right">Պատվերներ (ընդամենը)</Th>
          <Th className="w-56" />
        </Thead>
        <tbody>
          {employees.map((employee) =>
            editing === employee.id ? (
              <EmployeeForm
                key={employee.id}
                employee={employee}
                onDone={(saved) => {
                  setEditing(null);
                  if (saved) router.refresh();
                }}
              />
            ) : (
              <Tr key={employee.id}>
                <Td className="font-medium">
                  {employee.name}
                  {!employee.isActive ? (
                    <Badge className="ml-2" tone="default">
                      Չի աշխատում
                    </Badge>
                  ) : null}
                </Td>
                <Td className="text-ink-muted">{employee.position ?? "—"}</Td>
                <Td className="text-ink-muted">{employee.phone ?? "—"}</Td>
                <Td className="text-right tabular-nums">{employee.ordersCount ?? 0}</Td>
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
          {employees.length === 0 ? <EmptyRow colSpan={5}>Աշխատակիցներ դեռ չկան։ Ավելացրեք առաջինը։</EmptyRow> : null}
          <EmployeeForm onDone={(saved) => saved && router.refresh()} />
        </tbody>
      </TableWrap>
    </div>
  );
}

/** A new employee (the last row of the list) or an employee being edited. */
function EmployeeForm({ employee, onDone }: { employee?: AdminEmployeeDto; onDone: (saved: boolean) => void }) {
  const empty: Draft = { name: "", position: "", phone: "" };
  const [draft, setDraft] = useState<Draft>(
    employee ? { name: employee.name, position: employee.position ?? "", phone: employee.phone ?? "" } : empty,
  );
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [pending, setPending] = useState(false);

  async function save() {
    setPending(true);
    const result = await send(employee ? `/api/admin/employees/${employee.id}` : "/api/admin/employees", employee ? "PATCH" : "POST", {
      name: draft.name,
      position: draft.position || null,
      phone: draft.phone || null,
    });
    setPending(false);
    setErrors(result.errors);
    if (!result.ok) return;
    if (!employee) setDraft(empty);
    onDone(true);
  }

  const field = (key: keyof Draft, placeholder: string) => (
    <td className="px-2 py-2 align-top">
      <Input
        value={draft[key]}
        placeholder={placeholder}
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
      {field("name", employee ? "Անուն" : "Նոր աշխատակցի անունը")}
      {field("position", "Պաշտոն (օր.՝ վարպետ)")}
      {field("phone", "Հեռախոս")}
      <td />
      <td className="whitespace-nowrap px-2 py-2 text-right align-top">
        <Button size="sm" onClick={save} disabled={pending || draft.name.trim() === ""}>
          {employee ? "Պահպանել" : "+ Ավելացնել"}
        </Button>
        {employee ? (
          <Button size="sm" variant="ghost" onClick={() => onDone(false)} disabled={pending}>
            Չեղարկել
          </Button>
        ) : null}
      </td>
    </tr>
  );
}

/** A name found in the performer column, made an employee in one click. */
export function AddEmployeeButton({ name }: { name: string }) {
  const router = useRouter();
  const [pending, setPending] = useState(false);

  return (
    <button
      type="button"
      className="font-medium text-primary underline disabled:opacity-50"
      disabled={pending}
      onClick={async () => {
        setPending(true);
        const result = await send("/api/admin/employees", "POST", { name });
        setPending(false);
        if (result.ok) router.refresh();
      }}
    >
      Ավելացնել աշխատակիցների մեջ
    </button>
  );
}
