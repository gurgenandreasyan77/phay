"use client";

import { useState } from "react";
import { useRouter } from "next/navigation";
import type { AdminContactChannelDto } from "@/lib/adminApi";
import { Button, EmptyRow, Input, Select, TableWrap, Th, Thead, Td, Tr } from "@/ui";

type ChannelType = AdminContactChannelDto["type"];

const TYPE_LABELS: Record<ChannelType, string> = {
  phone: "Հեռախոս",
  whatsapp: "WhatsApp",
  telegram: "Telegram",
  instagram: "Instagram",
  facebook: "Facebook",
  tiktok: "TikTok",
  viber: "Viber",
  email: "Էլ. փոստ",
  other: "Այլ",
};

const TYPES = Object.keys(TYPE_LABELS) as ChannelType[];

export function ContactChannelsManager({ channels }: { channels: AdminContactChannelDto[] }) {
  const router = useRouter();
  const sorted = [...channels].sort((a, b) => a.sortOrder - b.sortOrder);

  return (
    <div className="space-y-4">
      <TableWrap>
        <Thead>
          <Th>Տեսակ</Th>
          <Th>Արժեք</Th>
          <Th>Պիտակ</Th>
          <Th>Կարգ</Th>
          <Th>Հիմնական</Th>
          <Th>Ակտիվ</Th>
          <Th />
        </Thead>
        <tbody>
          {sorted.map((channel) => (
            <ContactChannelRow key={channel.id} channel={channel} onChanged={() => router.refresh()} />
          ))}
          {sorted.length === 0 ? <EmptyRow colSpan={7}>Կապի եղանակներ դեռ չկան։</EmptyRow> : null}
        </tbody>
      </TableWrap>

      <NewChannelForm onCreated={() => router.refresh()} />
    </div>
  );
}

function ContactChannelRow({
  channel,
  onChanged,
}: {
  channel: AdminContactChannelDto;
  onChanged: () => void;
}) {
  const [type, setType] = useState<ChannelType>(channel.type);
  const [value, setValue] = useState(channel.value);
  const [label, setLabel] = useState(channel.label ?? "");
  const [sortOrder, setSortOrder] = useState(String(channel.sortOrder));
  const [isPrimary, setIsPrimary] = useState(channel.isPrimary);
  const [isActive, setIsActive] = useState(channel.isActive);
  const [pending, setPending] = useState(false);

  const dirty =
    type !== channel.type ||
    value !== channel.value ||
    label !== (channel.label ?? "") ||
    sortOrder !== String(channel.sortOrder) ||
    isPrimary !== channel.isPrimary ||
    isActive !== channel.isActive;

  async function save() {
    setPending(true);
    try {
      await fetch(`/api/admin/contact-channels/${channel.id}`, {
        method: "PATCH",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          type,
          value,
          label: label || null,
          sort_order: Number(sortOrder) || 0,
          is_primary: isPrimary,
          is_active: isActive,
        }),
      });
      onChanged();
    } finally {
      setPending(false);
    }
  }

  async function remove() {
    if (!confirm("Ջնջե՞լ այս կապի եղանակը։")) return;
    setPending(true);
    try {
      await fetch(`/api/admin/contact-channels/${channel.id}`, { method: "DELETE" });
      onChanged();
    } finally {
      setPending(false);
    }
  }

  return (
    <Tr>
      <Td>
        <Select value={type} disabled={pending} onChange={(e) => setType(e.target.value as ChannelType)}>
          {TYPES.map((t) => (
            <option key={t} value={t}>
              {TYPE_LABELS[t]}
            </option>
          ))}
        </Select>
      </Td>
      <Td>
        <Input value={value} disabled={pending} onChange={(e) => setValue(e.target.value)} />
      </Td>
      <Td>
        <Input value={label} disabled={pending} onChange={(e) => setLabel(e.target.value)} />
      </Td>
      <Td>
        <Input
          type="number"
          value={sortOrder}
          disabled={pending}
          onChange={(e) => setSortOrder(e.target.value)}
          className="w-20"
        />
      </Td>
      <Td className="text-center">
        <input
          type="checkbox"
          checked={isPrimary}
          disabled={pending}
          onChange={(e) => setIsPrimary(e.target.checked)}
          className="h-4 w-4 accent-primary"
        />
      </Td>
      <Td className="text-center">
        <input
          type="checkbox"
          checked={isActive}
          disabled={pending}
          onChange={(e) => setIsActive(e.target.checked)}
          className="h-4 w-4 accent-primary"
        />
      </Td>
      <Td>
        <div className="flex items-center gap-2">
          <Button size="sm" onClick={save} disabled={pending || !dirty}>
            Պահպանել
          </Button>
          <Button size="sm" variant="secondary" onClick={remove} disabled={pending}>
            Ջնջել
          </Button>
        </div>
      </Td>
    </Tr>
  );
}

function NewChannelForm({ onCreated }: { onCreated: () => void }) {
  const [type, setType] = useState<ChannelType>("phone");
  const [value, setValue] = useState("");
  const [label, setLabel] = useState("");
  const [status, setStatus] = useState<"idle" | "saving" | "error">("idle");

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault();
    if (!value.trim()) return;
    setStatus("saving");

    try {
      const response = await fetch("/api/admin/contact-channels", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ type, value: value.trim(), label: label.trim() || undefined }),
      });

      if (!response.ok) {
        setStatus("error");
        return;
      }

      setValue("");
      setLabel("");
      setStatus("idle");
      onCreated();
    } catch {
      setStatus("error");
    }
  }

  return (
    <form onSubmit={handleSubmit} className="flex flex-wrap items-end gap-3 rounded-lg border border-border bg-surface p-4">
      <div className="space-y-1.5">
        <label className="text-sm font-medium text-ink">Տեսակ</label>
        <Select value={type} onChange={(e) => setType(e.target.value as ChannelType)}>
          {TYPES.map((t) => (
            <option key={t} value={t}>
              {TYPE_LABELS[t]}
            </option>
          ))}
        </Select>
      </div>
      <div className="space-y-1.5">
        <label className="text-sm font-medium text-ink">Արժեք</label>
        <Input value={value} onChange={(e) => setValue(e.target.value)} placeholder="+374 XX XXXXXX" />
      </div>
      <div className="space-y-1.5">
        <label className="text-sm font-medium text-ink">Պիտակ (ոչ պարտադիր)</label>
        <Input value={label} onChange={(e) => setLabel(e.target.value)} />
      </div>
      <Button type="submit" disabled={status === "saving"}>
        Ավելացնել
      </Button>
      {status === "error" ? <span className="text-sm text-danger">Չհաջողվեց ավելացնել։</span> : null}
    </form>
  );
}
