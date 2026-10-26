"use client";

import { useState } from "react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { FolderPlus, Pencil, Trash2, UserPlus } from "lucide-react";
import type { AdminEmployeeDto, AdminEmployeeGroupDto } from "@/lib/adminApi";
import { Badge, Button, Card, EmptyRow, FieldLabel, Input, Select, TableWrap, Td, Th, Thead, Tr } from "@/ui";
import { validationErrors } from "../_components/tables/shared";

type Draft = { name: string; position: string; phone: string; groupId: string };

const EMPTY: Draft = { name: "", position: "", phone: "", groupId: "" };

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
  return {
    name: draft.name,
    position: draft.position || null,
    phone: draft.phone || null,
    groupId: draft.groupId ? Number(draft.groupId) : null,
  };
}

/** A group to work in, or none. */
function GroupSelect({ groups, value, onChange }: { groups: AdminEmployeeGroupDto[]; value: string; onChange: (value: string) => void }) {
  return (
    <Select value={value} onChange={(e) => onChange(e.target.value)}>
      <option value="">Առանց խմբի</option>
      {groups.map((g) => (
        <option key={g.id} value={g.id}>
          {g.manager ? `${g.name} (մենեջեր՝ ${g.manager.name})` : g.name}
        </option>
      ))}
    </Select>
  );
}

/** Adding an employee: always at the top of the page. */
export function NewEmployeeForm({ groups }: { groups: AdminEmployeeGroupDto[] }) {
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

  const field = (key: "name" | "position" | "phone", label: string, placeholder: string) => (
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
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
          {field("name", "Անուն *", "օր.՝ Աննա")}
          {field("position", "Պաշտոն", "օր.՝ վարպետ, մենեջեր")}
          {field("phone", "Հեռախոս", "+374…")}
          {groups.length > 0 ? (
            <label className="space-y-1.5">
              <FieldLabel>Խումբ</FieldLabel>
              <GroupSelect groups={groups} value={draft.groupId} onChange={(groupId) => setDraft((d) => ({ ...d, groupId }))} />
              {errors.groupId ? <p className="text-xs text-danger">{errors.groupId}</p> : null}
            </label>
          ) : null}
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
export function EmployeesList({ employees, groups }: { employees: AdminEmployeeDto[]; groups: AdminEmployeeGroupDto[] }) {
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
        <Th>Խումբ</Th>
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
              groups={groups}
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
                {employee.managedGroups.length > 0 ? (
                  <span className="mt-0.5 block text-xs text-ink-muted">
                    <Badge tone="info" className="mr-1.5">
                      Մենեջեր
                    </Badge>
                    {employee.managedGroups.map((g) => g.name).join(", ")}
                  </span>
                ) : null}
              </Td>
              <Td className="text-ink-muted">{employee.position ?? "—"}</Td>
              <Td className="text-ink-muted">
                {employee.group ? (
                  <>
                    <span className="text-ink">{employee.group.name}</span>
                    {employee.group.manager && employee.group.manager.id !== employee.id ? (
                      <span className="block text-xs text-ink-faint">մենեջեր՝ {employee.group.manager.name}</span>
                    ) : null}
                  </>
                ) : (
                  "—"
                )}
              </Td>
              <Td className="text-ink-muted">{employee.phone ?? "—"}</Td>
              <Td className="whitespace-nowrap text-right tabular-nums">
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
        {employees.length === 0 ? <EmptyRow colSpan={6}>Աշխատակիցներ դեռ չկան։ Ավելացրեք առաջինը վերևում։</EmptyRow> : null}
      </tbody>
    </TableWrap>
  );
}

function EditRow({
  employee,
  groups,
  onDone,
}: {
  employee: AdminEmployeeDto;
  groups: AdminEmployeeGroupDto[];
  onDone: (saved: boolean) => void;
}) {
  const [draft, setDraft] = useState<Draft>({
    name: employee.name,
    position: employee.position ?? "",
    phone: employee.phone ?? "",
    groupId: employee.group ? String(employee.group.id) : "",
  });
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [pending, setPending] = useState(false);

  async function save() {
    setPending(true);
    const result = await send(`/api/admin/employees/${employee.id}`, "PATCH", payload(draft));
    setPending(false);
    setErrors(result.errors);
    if (result.ok) onDone(true);
  }

  const field = (key: "name" | "position" | "phone") => (
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
      <td className="px-2 py-2 align-top">
        <GroupSelect groups={groups} value={draft.groupId} onChange={(groupId) => setDraft((d) => ({ ...d, groupId }))} />
        {errors.groupId ? <p className="mt-1 text-xs text-danger">{errors.groupId}</p> : null}
      </td>
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

/** A manager among the employees, or none. */
function ManagerSelect({ employees, value, onChange }: { employees: AdminEmployeeDto[]; value: string; onChange: (value: string) => void }) {
  return (
    <Select value={value} onChange={(e) => onChange(e.target.value)}>
      <option value="">Առանց մենեջերի</option>
      {employees
        .filter((e) => e.isActive || String(e.id) === value)
        .map((e) => (
          <option key={e.id} value={e.id}>
            {e.position ? `${e.name} · ${e.position}` : e.name}
          </option>
        ))}
    </Select>
  );
}

/**
 * Groups of employees, each with a manager. A manager is one of the
 * employees and may lead several groups.
 */
export function GroupsManager({ groups, employees }: { groups: AdminEmployeeGroupDto[]; employees: AdminEmployeeDto[] }) {
  const router = useRouter();
  const [draft, setDraft] = useState({ name: "", managerId: "" });
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [pending, setPending] = useState(false);
  const [editing, setEditing] = useState<number | null>(null);

  async function add() {
    setPending(true);
    const result = await send("/api/admin/employee-groups", "POST", {
      name: draft.name,
      managerId: draft.managerId ? Number(draft.managerId) : null,
    });
    setPending(false);
    setErrors(result.errors);
    if (!result.ok) return;
    setDraft({ name: "", managerId: "" });
    router.refresh();
  }

  async function remove(group: AdminEmployeeGroupDto) {
    const note = (group.employeesCount ?? 0) > 0 ? ` Նրա ${group.employeesCount} աշխատակիցները կմնան, առանց խմբի։` : "";
    if (!confirm(`Ջնջե՞լ «${group.name}» խումբը։${note}`)) return;
    if ((await send(`/api/admin/employee-groups/${group.id}`, "DELETE")).ok) router.refresh();
  }

  return (
    <div className="space-y-4">
      <form
        className="grid items-start gap-3 sm:grid-cols-[1fr_1fr_auto]"
        onSubmit={(e) => {
          e.preventDefault();
          add();
        }}
      >
        <div>
          <Input value={draft.name} placeholder="Խմբի անուն, օր.՝ Բազմոցներ" onChange={(e) => setDraft((d) => ({ ...d, name: e.target.value }))} />
          {errors.name ? <p className="mt-1 text-xs text-danger">{errors.name}</p> : null}
        </div>
        <div>
          <ManagerSelect employees={employees} value={draft.managerId} onChange={(managerId) => setDraft((d) => ({ ...d, managerId }))} />
          {errors.managerId ? <p className="mt-1 text-xs text-danger">{errors.managerId}</p> : null}
        </div>
        <Button type="submit" variant="secondary" disabled={pending}>
          <FolderPlus className="h-4 w-4" />
          Ավելացնել խումբ
        </Button>
      </form>

      {groups.length > 0 ? (
        <TableWrap>
          <Thead>
            <Th>Խումբ</Th>
            <Th>Մենեջեր</Th>
            <Th className="text-right">Աշխատակիցներ</Th>
            <Th className="w-28" />
          </Thead>
          <tbody>
            {groups.map((group) =>
              editing === group.id ? (
                <GroupEditRow
                  key={group.id}
                  group={group}
                  employees={employees}
                  onDone={(saved) => {
                    setEditing(null);
                    if (saved) router.refresh();
                  }}
                />
              ) : (
                <Tr key={group.id}>
                  <Td className="font-medium text-ink">{group.name}</Td>
                  <Td className="text-ink-muted">{group.manager?.name ?? "—"}</Td>
                  <Td className="whitespace-nowrap text-right tabular-nums">{group.employeesCount ?? 0}</Td>
                  <Td className="whitespace-nowrap text-right">
                    <Button variant="ghost" size="sm" aria-label="Խմբագրել" onClick={() => setEditing(group.id)}>
                      <Pencil className="h-4 w-4" />
                    </Button>
                    <Button variant="ghost" size="sm" aria-label="Ջնջել" onClick={() => remove(group)}>
                      <Trash2 className="h-4 w-4" />
                    </Button>
                  </Td>
                </Tr>
              ),
            )}
          </tbody>
        </TableWrap>
      ) : null}
    </div>
  );
}

function GroupEditRow({
  group,
  employees,
  onDone,
}: {
  group: AdminEmployeeGroupDto;
  employees: AdminEmployeeDto[];
  onDone: (saved: boolean) => void;
}) {
  const [name, setName] = useState(group.name);
  const [managerId, setManagerId] = useState(group.manager ? String(group.manager.id) : "");
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [pending, setPending] = useState(false);

  async function save() {
    setPending(true);
    const result = await send(`/api/admin/employee-groups/${group.id}`, "PATCH", { name, managerId: managerId ? Number(managerId) : null });
    setPending(false);
    setErrors(result.errors);
    if (result.ok) onDone(true);
  }

  return (
    <tr className="border-b border-border bg-primary-light/40 last:border-0">
      <td className="px-2 py-2 align-top">
        <Input value={name} onChange={(e) => setName(e.target.value)} onKeyDown={(e) => e.key === "Enter" && save()} />
        {errors.name ? <p className="mt-1 text-xs text-danger">{errors.name}</p> : null}
      </td>
      <td className="px-2 py-2 align-top">
        <ManagerSelect employees={employees} value={managerId} onChange={setManagerId} />
        {errors.managerId ? <p className="mt-1 text-xs text-danger">{errors.managerId}</p> : null}
      </td>
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
