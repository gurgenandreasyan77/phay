"use client";

import { useState } from "react";
import { useRouter } from "next/navigation";
import { ListChecks, Plus, X } from "lucide-react";
import type { OnboardingItemDto } from "@/lib/adminApi";
import { Button, Input, SectionCard } from "@/ui";

/**
 * A new employee's first steps (contract, tools, the first order with a
 * colleague...), ticked off as they go. Անյա fills it when she hires a
 * candidate; it can also be started here from the usual list.
 */
export function Onboarding({
  employeeId,
  items,
  defaults,
}: {
  employeeId: number;
  items: OnboardingItemDto[];
  defaults: string[];
}) {
  const router = useRouter();
  const [list, setList] = useState(items);
  const [title, setTitle] = useState("");
  const [failed, setFailed] = useState(false);

  async function save(next: OnboardingItemDto[]) {
    const before = list;
    setList(next);
    const response = await fetch(`/api/admin/employees/${employeeId}`, {
      method: "PATCH",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ onboarding: next.length > 0 ? next : null }),
    }).catch(() => null);
    setFailed(!response?.ok);
    if (!response?.ok) setList(before);
    else router.refresh();
  }

  const done = list.filter((i) => i.done).length;

  if (list.length === 0) {
    return (
      <SectionCard title="Ադապտացիա" description="Նոր աշխատակցի առաջին քայլերի ցուցակ՝ պայմանագիր, ծանոթացում, առաջին պատվերը։">
        <Button variant="secondary" onClick={() => save(defaults.map((t) => ({ title: t, done: false })))}>
          <ListChecks className="h-4 w-4" />
          Սկսել ստանդարտ ցուցակով
        </Button>
        {failed ? <p className="mt-2 text-sm text-danger">Չհաջողվեց պահպանել։</p> : null}
      </SectionCard>
    );
  }

  return (
    <SectionCard title={`Ադապտացիա · ${done}/${list.length}`}>
      <div className="mb-3 h-1.5 overflow-hidden rounded-full bg-surface-secondary">
        <div className="h-full rounded-full bg-success transition-[width]" style={{ width: `${(done / list.length) * 100}%` }} />
      </div>
      <ul className="space-y-1">
        {list.map((item, index) => (
          <li key={index} className="group flex items-center gap-3 rounded-md px-1 py-1.5 hover:bg-surface-secondary">
            <input
              type="checkbox"
              className="h-4 w-4 accent-[var(--color-primary)]"
              checked={item.done}
              aria-label={item.title}
              onChange={() => save(list.map((i, n) => (n === index ? { ...i, done: !i.done } : i)))}
            />
            <span className={item.done ? "flex-1 text-sm text-ink-faint line-through" : "flex-1 text-sm text-ink"}>
              {item.title}
            </span>
            <button
              type="button"
              aria-label="Հեռացնել"
              className="text-ink-faint opacity-0 hover:text-danger focus:opacity-100 group-hover:opacity-100"
              onClick={() => save(list.filter((_, n) => n !== index))}
            >
              <X className="h-4 w-4" />
            </button>
          </li>
        ))}
      </ul>
      <form
        className="mt-3 flex gap-2"
        onSubmit={(e) => {
          e.preventDefault();
          if (!title.trim()) return;
          save([...list, { title: title.trim(), done: false }]);
          setTitle("");
        }}
      >
        <Input value={title} placeholder="Նոր քայլ" onChange={(e) => setTitle(e.target.value)} />
        <Button type="submit" variant="secondary" aria-label="Ավելացնել">
          <Plus className="h-4 w-4" />
        </Button>
      </form>
      {failed ? <p className="mt-2 text-sm text-danger">Չհաջողվեց պահպանել։</p> : null}
    </SectionCard>
  );
}
