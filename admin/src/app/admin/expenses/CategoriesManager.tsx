"use client";

import { useState } from "react";
import { useRouter } from "next/navigation";
import { Pencil, Plus, Trash2 } from "lucide-react";
import type { ExpenseCategoryDto } from "@/lib/adminApi";
import { Badge, Button, Input, TableWrap, Td, Th, Thead, Tr } from "../_components/ui";
import { CATEGORY_HINTS, send } from "./shared";

/**
 * The kinds of expenses: the ready-made ones (renamed, not deleted) and the
 * business's own, each counted in the net profit or kept out of it.
 */
export function CategoriesManager({ categories }: { categories: ExpenseCategoryDto[] }) {
  const router = useRouter();
  const [name, setName] = useState("");
  const [error, setError] = useState<string | null>(null);
  const [editing, setEditing] = useState<number | null>(null);
  const [editName, setEditName] = useState("");
  const [pending, setPending] = useState(false);

  async function run(url: string, method: string, body?: unknown): Promise<boolean> {
    setPending(true);
    setError(null);
    const result = await send(url, method, body);
    setPending(false);
    if (!result.ok) {
      setError(Object.values(result.errors)[0] ?? "Չհաջողվեց պահպանել։");
      return false;
    }
    router.refresh();
    return true;
  }

  async function add() {
    if (await run("/api/admin/expense-categories", "POST", { name, inProfit: true })) setName("");
  }

  async function rename(category: ExpenseCategoryDto) {
    if (editName.trim() === category.name || (await run(`/api/admin/expense-categories/${category.id}`, "PATCH", { name: editName }))) {
      setEditing(null);
    }
  }

  async function remove(category: ExpenseCategoryDto) {
    if (!confirm(`Ջնջե՞լ «${category.name}» տեսակը։ Այն նշած տողերը կմնան և կհաշվվեն շահույթում իրենց անունով։`)) return;
    await run(`/api/admin/expense-categories/${category.id}`, "DELETE");
  }

  return (
    <div className="space-y-3">
      <TableWrap>
        <Thead>
          <Th>Տեսակ</Th>
          <Th className="w-60">Հաշվել շահույթում</Th>
          <Th className="w-28" />
        </Thead>
        <tbody>
          {categories.map((category) => (
            <Tr key={category.id}>
              <Td>
                {editing === category.id ? (
                  <form
                    className="flex gap-2"
                    onSubmit={(e) => {
                      e.preventDefault();
                      rename(category);
                    }}
                  >
                    <Input value={editName} onChange={(e) => setEditName(e.target.value)} autoFocus />
                    <Button type="submit" size="sm" disabled={pending}>
                      Պահպանել
                    </Button>
                    <Button type="button" size="sm" variant="ghost" onClick={() => setEditing(null)}>
                      Չեղարկել
                    </Button>
                  </form>
                ) : (
                  <div>
                    <span className="font-medium text-ink">{category.name}</span>
                    {category.isDefault ? null : (
                      <Badge tone="info" className="ml-2">
                        Ձեր
                      </Badge>
                    )}
                    {category.key && CATEGORY_HINTS[category.key] ? (
                      <p className="mt-0.5 max-w-xl text-xs text-ink-muted">{CATEGORY_HINTS[category.key]}</p>
                    ) : null}
                  </div>
                )}
              </Td>
              <Td>
                <label className="inline-flex cursor-pointer items-center gap-2 text-sm">
                  <input
                    type="checkbox"
                    className="h-4 w-4 accent-primary"
                    checked={category.inProfit}
                    disabled={pending}
                    onChange={(e) => run(`/api/admin/expense-categories/${category.id}`, "PATCH", { inProfit: e.target.checked })}
                  />
                  {category.inProfit ? "Այո" : <span className="text-ink-muted">Ոչ, միայն մնացորդում</span>}
                </label>
              </Td>
              <Td className="text-right">
                <div className="flex justify-end gap-1">
                  <Button
                    size="sm"
                    variant="ghost"
                    aria-label="Վերանվանել"
                    onClick={() => {
                      setEditing(category.id);
                      setEditName(category.name);
                    }}
                  >
                    <Pencil className="h-4 w-4" />
                  </Button>
                  {category.isDefault ? null : (
                    <Button size="sm" variant="ghost" aria-label="Ջնջել" onClick={() => remove(category)} disabled={pending}>
                      <Trash2 className="h-4 w-4" />
                    </Button>
                  )}
                </div>
              </Td>
            </Tr>
          ))}
        </tbody>
      </TableWrap>
      <form
        className="flex flex-wrap items-center gap-2"
        onSubmit={(e) => {
          e.preventDefault();
          add();
        }}
      >
        <Input value={name} onChange={(e) => setName(e.target.value)} placeholder="Նոր տեսակ, օր.՝ Ֆրանչայզի վճար" className="max-w-sm" />
        <Button type="submit" variant="secondary" disabled={pending || name.trim() === ""}>
          <Plus className="h-4 w-4" />
          Ավելացնել տեսակ
        </Button>
        {error ? <span className="text-sm text-danger">{error}</span> : null}
      </form>
    </div>
  );
}
