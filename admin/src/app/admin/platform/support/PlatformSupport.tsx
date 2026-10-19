"use client";

import { useCallback, useEffect, useMemo, useState } from "react";
import { useRouter } from "next/navigation";
import type { SupportConversationDto } from "@/lib/adminApi";
import { BusinessLogo } from "../../_components/BusinessLogo";
import { ChatThread } from "../../_components/chat/ChatThread";
import { notifySupportActivity } from "../../_components/chat/unread";
import { EmptyCard, Select, cn } from "../../_components/ui";

type TenantRef = { id: number; name: string; logo: string | null };

const POLL_MS = 15_000;

/** The list of conversations on the left, the chosen business's chat on the right. */
export function PlatformSupport({
  initialConversations,
  tenants,
  initialTenantId,
}: {
  initialConversations: SupportConversationDto[];
  tenants: TenantRef[];
  initialTenantId: number | null;
}) {
  const router = useRouter();
  const [conversations, setConversations] = useState(initialConversations);
  const [tenantId, setTenantId] = useState<number | null>(
    initialTenantId ?? initialConversations[0]?.tenant.id ?? null,
  );

  const refresh = useCallback(async () => {
    try {
      const response = await fetch("/api/admin/support/conversations", { cache: "no-store" });
      if (!response.ok) return;
      const json = (await response.json()) as { data: SupportConversationDto[] | null };
      setConversations(json.data ?? []);
    } catch {
      // Keeps the list it has.
    }
  }, []);

  useEffect(() => {
    const timer = setInterval(() => {
      if (document.visibilityState === "visible") refresh();
    }, POLL_MS);
    return () => clearInterval(timer);
  }, [refresh]);

  const onActivity = useCallback(() => {
    refresh();
    notifySupportActivity();
  }, [refresh]);

  function choose(id: number) {
    setTenantId(id);
    router.replace(`/admin/platform/support?tenant=${id}`, { scroll: false });
  }

  const current = useMemo(
    () => tenants.find((t) => t.id === tenantId) ?? conversations.find((c) => c.tenant.id === tenantId)?.tenant ?? null,
    [tenants, conversations, tenantId],
  );
  // Businesses nobody has written to yet: a conversation can be started with them too.
  const silent = tenants.filter((t) => !conversations.some((c) => c.tenant.id === t.id));

  return (
    <div className="grid gap-4 lg:grid-cols-[20rem_1fr]">
      <div className="space-y-3">
        <div className="overflow-hidden rounded-lg border border-border bg-surface">
          {conversations.length === 0 ? (
            <p className="px-4 py-6 text-center text-sm text-ink-muted">Զրույցներ դեռ չկան։</p>
          ) : (
            <ul className="max-h-[28rem] divide-y divide-border overflow-y-auto">
              {conversations.map((conversation) => (
                <li key={conversation.tenant.id}>
                  <button
                    type="button"
                    onClick={() => choose(conversation.tenant.id)}
                    className={cn(
                      "flex w-full items-center gap-3 px-3 py-2.5 text-left hover:bg-surface-secondary",
                      conversation.tenant.id === tenantId && "bg-surface-secondary",
                    )}
                  >
                    <BusinessLogo name={conversation.tenant.name} logo={conversation.tenant.logo} size="sm" />
                    <span className="min-w-0 flex-1">
                      <span className="block truncate text-sm font-medium text-ink">{conversation.tenant.name}</span>
                      <span className="block truncate text-xs text-ink-muted">
                        {conversation.lastMessage.fromPlatform ? "Դուք՝ " : ""}
                        {conversation.lastMessage.body}
                      </span>
                    </span>
                    {conversation.unread > 0 ? (
                      <span className="rounded-full bg-primary px-1.5 py-0.5 text-[11px] font-semibold leading-none text-primary-foreground">
                        {conversation.unread}
                      </span>
                    ) : null}
                  </button>
                </li>
              ))}
            </ul>
          )}
        </div>

        {silent.length > 0 ? (
          <Select
            value=""
            onChange={(e) => {
              if (e.target.value) choose(Number(e.target.value));
            }}
          >
            <option value="">Գրել այլ բիզնեսի…</option>
            {silent.map((tenant) => (
              <option key={tenant.id} value={tenant.id}>
                {tenant.name}
              </option>
            ))}
          </Select>
        ) : null}
      </div>

      {current ? (
        <div className="space-y-3">
          <div className="flex items-center gap-3">
            <BusinessLogo name={current.name} logo={current.logo} />
            <p className="text-base font-semibold text-ink">{current.name}</p>
          </div>
          <ChatThread
            key={current.id}
            url={`/api/admin/tenants/${current.id}/messages`}
            side="platform"
            emptyText="Այս բիզնեսի հետ դեռ հաղորդագրություններ չկան։"
            onActivity={onActivity}
          />
        </div>
      ) : (
        <EmptyCard>Ընտրեք բիզնես՝ զրույցը բացելու համար։</EmptyCard>
      )}
    </div>
  );
}
