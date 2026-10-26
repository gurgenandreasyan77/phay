"use client";

import { useMemo, useState } from "react";
import { useRouter } from "next/navigation";
import Link from "next/link";
import type { AdminProductDto, AdminProductStatus } from "@/lib/adminApi";
import { Badge, type BadgeTone, EmptyRow, Input, TableWrap, Th, Thead, Td, Tr } from "@/ui";

const STATUS_LABELS: Record<AdminProductStatus, string> = {
  draft: "Draft",
  published: "Published",
  archived: "Archived",
};

const STATUS_TONES: Record<AdminProductStatus, BadgeTone> = {
  draft: "default",
  published: "success",
  archived: "pending",
};

export function ProductsList({ items }: { items: AdminProductDto[] }) {
  const router = useRouter();
  const [search, setSearch] = useState("");
  const [pendingId, setPendingId] = useState<number | null>(null);

  const sorted = useMemo(() => [...items].sort((a, b) => a.sortOrder - b.sortOrder), [items]);

  const filtered = useMemo(() => {
    const query = search.trim().toLowerCase();
    if (!query) return sorted;
    return sorted.filter(
      (item) =>
        item.slugHy.toLowerCase().includes(query) ||
        (item.name.hy ?? "").toLowerCase().includes(query) ||
        (item.name.ru ?? "").toLowerCase().includes(query) ||
        (item.brand ?? "").toLowerCase().includes(query),
    );
  }, [sorted, search]);

  async function remove(id: number) {
    if (!confirm("Ջնջե՞լ այս ապրանքը։")) return;
    setPendingId(id);
    try {
      const response = await fetch(`/api/admin/products/${id}`, { method: "DELETE" });
      if (response.ok) router.refresh();
    } finally {
      setPendingId(null);
    }
  }

  return (
    <div className="space-y-4">
      <Input
        value={search}
        onChange={(e) => setSearch(e.target.value)}
        placeholder="Փնտրել slug, անուն կամ բրենդ..."
        className="max-w-sm"
      />

      <TableWrap>
        <Thead>
          <Th>Slug</Th>
          <Th>Անուն</Th>
          <Th>Կարգավիճակ</Th>
          <Th>Գին</Th>
          <Th>Ուշադրության կարիք</Th>
          <Th />
        </Thead>
        <tbody>
          {filtered.map((item) => (
            <Tr key={item.id}>
              <Td className="font-mono text-xs text-ink-muted">{item.slugHy}</Td>
              <Td>{item.name.hy ?? item.name.ru ?? item.name.en}</Td>
              <Td>
                <Badge tone={STATUS_TONES[item.status]}>{STATUS_LABELS[item.status]}</Badge>
              </Td>
              <Td className="text-ink-muted">{item.priceAmount ?? "—"}</Td>
              <Td className="text-xs text-warning">{item.adminNote ? "Այո" : ""}</Td>
              <Td className="whitespace-nowrap text-right">
                <div className="flex items-center justify-end gap-3">
                  <Link href={`/admin/products/${item.id}`} className="text-xs font-medium text-primary hover:underline">
                    Խմբագրել
                  </Link>
                  <button
                    type="button"
                    onClick={() => remove(item.id)}
                    disabled={pendingId === item.id}
                    className="text-xs font-medium text-ink-muted hover:text-danger hover:underline disabled:opacity-40"
                  >
                    Ջնջել
                  </button>
                </div>
              </Td>
            </Tr>
          ))}
          {filtered.length === 0 ? <EmptyRow colSpan={6}>Ապրանքներ չեն գտնվել։</EmptyRow> : null}
        </tbody>
      </TableWrap>
    </div>
  );
}
