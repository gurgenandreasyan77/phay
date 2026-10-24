"use client";

import { useState } from "react";
import { Check, History, MessageSquare, Pencil, Plus, Trash2, X } from "lucide-react";
import type { AssistantConversationDto } from "@/lib/adminApi";
import { Button, Card, cn, Input } from "../_components/ui";
import { ConfirmDialog } from "../_components/dialog/ConfirmDialog";
import { timeAgo } from "../_components/header/notifications";

/**
 * The person's earlier chats with the assistant: open one to read it again
 * and continue, rename or delete it. A column beside the chat on wide
 * screens, a list that opens above it on a phone.
 */
export function AssistantHistory({
  conversations,
  activeId,
  busy,
  onOpen,
  onNew,
  onRenamed,
  onDeleted,
}: {
  conversations: AssistantConversationDto[];
  activeId: number | null;
  /** A question is being answered: switching would lose it. */
  busy: boolean;
  onOpen: (id: number) => void;
  onNew: () => void;
  onRenamed: (conversation: AssistantConversationDto) => void;
  onDeleted: (id: number) => void;
}) {
  const [open, setOpen] = useState(false);
  const [editing, setEditing] = useState<{ id: number; title: string } | null>(null);
  const [deleting, setDeleting] = useState<AssistantConversationDto | null>(null);
  const [pending, setPending] = useState(false);
  const [error, setError] = useState<string | null>(null);

  async function rename() {
    if (!editing) return;
    const title = editing.title.trim();
    const current = conversations.find((c) => c.id === editing.id);
    if (!title || title === current?.title) {
      setEditing(null);
      return;
    }
    setPending(true);
    const response = await fetch(`/api/admin/assistant/conversations/${editing.id}`, {
      method: "PATCH",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ title }),
    }).catch(() => null);
    setPending(false);
    if (response?.ok) {
      const json = await response.json();
      onRenamed(json.data);
      setEditing(null);
    }
  }

  async function remove() {
    if (!deleting) return;
    setPending(true);
    setError(null);
    const response = await fetch(`/api/admin/assistant/conversations/${deleting.id}`, { method: "DELETE" }).catch(
      () => null,
    );
    setPending(false);
    if (!response?.ok) {
      setError("Չհաջողվեց ջնջել։ Փորձեք կրկին։");
      return;
    }
    onDeleted(deleting.id);
    setDeleting(null);
  }

  const list = (
    <div className="space-y-3">
      <Button
        variant="secondary"
        size="sm"
        className="w-full"
        disabled={busy}
        onClick={() => {
          onNew();
          setOpen(false);
        }}
      >
        <Plus className="h-4 w-4" />
        Նոր զրույց
      </Button>

      {conversations.length === 0 ? (
        <p className="px-1 text-xs leading-relaxed text-ink-muted">
          Ձեր հարցերն ու պատասխանները կպահվեն այստեղ, և կարող եք ցանկացած պահի վերադառնալ դրանց։
        </p>
      ) : (
        <ul className="-mx-1 max-h-[60vh] space-y-0.5 overflow-y-auto lg:max-h-[calc(100vh-16rem)]">
          {conversations.map((conversation) => {
            const active = conversation.id === activeId;
            if (editing?.id === conversation.id) {
              return (
                <li key={conversation.id}>
                  <form
                    className="flex items-center gap-1 px-1 py-1"
                    onSubmit={(e) => {
                      e.preventDefault();
                      rename();
                    }}
                  >
                    <Input
                      value={editing.title}
                      onChange={(e) => setEditing({ ...editing, title: e.target.value })}
                      onKeyDown={(e) => e.key === "Escape" && setEditing(null)}
                      maxLength={200}
                      autoFocus
                      aria-label="Զրույցի անուն"
                      className="h-8 text-sm"
                    />
                    <Button
                      type="submit"
                      variant="ghost"
                      size="sm"
                      className="px-2"
                      disabled={pending}
                      aria-label="Պահպանել"
                    >
                      <Check className="h-4 w-4" />
                    </Button>
                    <Button
                      variant="ghost"
                      size="sm"
                      className="px-2"
                      onClick={() => setEditing(null)}
                      aria-label="Չեղարկել"
                    >
                      <X className="h-4 w-4" />
                    </Button>
                  </form>
                </li>
              );
            }
            return (
              <li key={conversation.id} className="group relative">
                <button
                  type="button"
                  disabled={busy}
                  onClick={() => {
                    onOpen(conversation.id);
                    setOpen(false);
                  }}
                  aria-current={active ? "true" : undefined}
                  className={cn(
                    "flex w-full items-start gap-2 rounded-md px-2 py-2 pr-14 text-left transition-colors disabled:cursor-not-allowed",
                    active ? "bg-primary-light text-ink" : "text-ink hover:bg-surface-secondary",
                  )}
                >
                  <MessageSquare
                    className={cn("mt-0.5 h-4 w-4 shrink-0", active ? "text-primary" : "text-ink-faint")}
                  />
                  <span className="min-w-0 flex-1">
                    <span className="line-clamp-2 text-sm">{conversation.title}</span>
                    <span className="mt-0.5 block text-xs text-ink-faint" suppressHydrationWarning>
                      {timeAgo(conversation.updatedAt)} · {Math.ceil(conversation.messages / 2)} հարց
                    </span>
                  </span>
                </button>
                <span className="absolute right-1 top-1.5 flex opacity-100 transition-opacity lg:opacity-0 lg:group-focus-within:opacity-100 lg:group-hover:opacity-100">
                  <button
                    type="button"
                    className="rounded p-1.5 text-ink-faint hover:bg-surface hover:text-ink"
                    onClick={() =>
                      setEditing({
                        id: conversation.id,
                        title: conversation.title,
                      })
                    }
                    aria-label="Վերանվանել"
                    title="Վերանվանել"
                  >
                    <Pencil className="h-3.5 w-3.5" />
                  </button>
                  <button
                    type="button"
                    className="rounded p-1.5 text-ink-faint hover:bg-surface hover:text-danger"
                    onClick={() => {
                      setError(null);
                      setDeleting(conversation);
                    }}
                    aria-label="Ջնջել"
                    title="Ջնջել"
                  >
                    <Trash2 className="h-3.5 w-3.5" />
                  </button>
                </span>
              </li>
            );
          })}
        </ul>
      )}
    </div>
  );

  return (
    <>
      {/* Phone and tablet: a button that opens the list above the chat. */}
      <div className="lg:hidden">
        <Button variant="secondary" size="sm" onClick={() => setOpen((v) => !v)} aria-expanded={open}>
          <History className="h-4 w-4" />
          Պատմություն
          {conversations.length > 0 ? ` (${conversations.length})` : ""}
        </Button>
        {open ? <Card className="mt-2">{list}</Card> : null}
      </div>

      <Card className="hidden lg:sticky lg:top-20 lg:block">
        <p className="mb-3 flex items-center gap-2 text-sm font-medium text-ink">
          <History className="h-4 w-4 text-ink-muted" />
          Պատմություն
        </p>
        {list}
      </Card>

      <ConfirmDialog
        open={deleting !== null}
        content={{
          title: "Ջնջե՞լ զրույցը",
          description: deleting
            ? `«${deleting.title}» զրույցի բոլոր հարցերն ու պատասխանները կջնջվեն։ Սա հնարավոր չէ հետ բերել։`
            : undefined,
          confirmLabel: "Ջնջել",
          tone: "danger",
        }}
        pending={pending}
        error={error}
        onConfirm={remove}
        onCancel={() => setDeleting(null)}
      />
    </>
  );
}
