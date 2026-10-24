"use client";

import { useState } from "react";
import { useRouter } from "next/navigation";
import type { FeedbackMessageDto } from "@/lib/adminApi";
import { formatDateTime } from "../../_components/tables/shared";
import { Badge, Card, buttonClasses } from "@/ui";

export function FeedbackList({ items }: { items: FeedbackMessageDto[] }) {
  return (
    <div className="space-y-3">
      {items.map((item) => (
        <FeedbackCard key={item.id} item={item} />
      ))}
    </div>
  );
}

function FeedbackCard({ item }: { item: FeedbackMessageDto }) {
  const router = useRouter();
  const [pending, setPending] = useState(false);
  const [error, setError] = useState(false);
  const unread = item.readAt === null;

  async function send(init: RequestInit) {
    setPending(true);
    setError(false);
    try {
      const response = await fetch(`/api/admin/feedback/${item.id}`, init);
      if (!response.ok) {
        setError(true);
        return;
      }
      router.refresh();
    } catch {
      setError(true);
    } finally {
      setPending(false);
    }
  }

  return (
    <Card className="space-y-2">
      <div className="flex flex-wrap items-center gap-2">
        <p className="text-sm font-semibold text-ink">{item.name}</p>
        {unread ? <Badge tone="success">Նոր</Badge> : null}
        <span className="text-xs text-ink-faint">{formatDateTime(item.createdAt)}</span>
      </div>
      <p className="text-sm text-ink-muted">
        {item.phone ? (
          <a href={`tel:${item.phone}`} className="text-primary hover:underline">
            {item.phone}
          </a>
        ) : null}
        {item.phone && item.email ? " · " : null}
        {item.email ? (
          <a href={`mailto:${item.email}`} className="text-primary hover:underline">
            {item.email}
          </a>
        ) : null}
      </p>
      <p className="whitespace-pre-line text-sm text-ink">{item.message}</p>
      <div className="flex flex-wrap items-center gap-3 pt-1">
        <button
          type="button"
          disabled={pending}
          onClick={() =>
            send({ method: "PATCH", headers: { "Content-Type": "application/json" }, body: JSON.stringify({ read: unread }) })
          }
          className={buttonClasses("secondary", "sm")}
        >
          {unread ? "Նշել կարդացված" : "Նշել չկարդացված"}
        </button>
        <button
          type="button"
          disabled={pending}
          onClick={() => {
            if (confirm("Ջնջե՞լ հաղորդագրությունը։")) send({ method: "DELETE" });
          }}
          className={buttonClasses("ghost", "sm")}
        >
          Ջնջել
        </button>
        {error ? <span className="text-xs text-danger">Չհաջողվեց։</span> : null}
      </div>
    </Card>
  );
}
