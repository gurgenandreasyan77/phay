"use client";

import { Fragment, useEffect, useRef, useState, type ReactNode } from "react";
import { Paperclip, Send, Sparkles } from "lucide-react";
import type {
  AgentDto,
  AssistantAttachmentDto,
  AssistantConversationDetailDto,
  AssistantConversationDto,
  AssistantMessageDto,
  AssistantPlanOfferDto,
  AssistantUsageDto,
} from "@/lib/adminApi";
import { Button, Card, cn, Textarea } from "@/ui";
import { AgentAvatar, agentTintStyle } from "../_components/agents/AgentAvatar";
import agentStyles from "../_components/agents/agents.module.css";
import { AssistantHistory } from "./AssistantHistory";
import { AssistantLimitReached, AssistantQuotaBar } from "./AssistantQuota";
import {
  ACCEPT,
  checkFile,
  FILE_ERRORS,
  MAX_FILES,
  MessageFiles,
  PendingFiles,
  shownFromAttachment,
  type ShownFile,
} from "./Attachments";
import { SUGGESTIONS } from "./suggestions";

type Message = { role: "user" | "assistant"; content: string; files?: ShownFile[] };

const REFUSED = "Այս հարցին չեմ կարող պատասխանել։ Փորձեք ձևակերպել այլ կերպ։";

/** A kept question or answer as the chat shows it. */
function toMessage(message: Pick<AssistantMessageDto, "role" | "content" | "refused" | "attachments">): Message {
  if (message.role === "user") {
    return { role: "user", content: message.content, files: (message.attachments ?? []).map(shownFromAttachment) };
  }
  return {
    role: "assistant",
    content: message.refused ? REFUSED : message.content || "Պատասխանը դատարկ է։ Փորձեք կրկին։",
  };
}

/** ?c=<id> keeps the open conversation on reload and in a shared link to oneself. */
function rememberInUrl(id: number | null) {
  const url = new URL(window.location.href);
  if (id === null) url.searchParams.delete("c");
  else url.searchParams.set("c", String(id));
  window.history.replaceState(window.history.state, "", url);
}

/** The first question of an agent that still has to learn the business. */
const GET_ACQUAINTED = "Եկեք ծանոթանանք. ուզում եմ պատմել իմ բիզնեսի մասին";

const ERRORS: Record<string, string> = {
  not_configured: "AI օգնականը դեռ միացված չէ։",
  agent_inactive: "Այս AI աշխատակիցը դեռ ձեր թիմում չէ։",
  limit_reached: "Այս ամսվա հարցերը սպառվել են։",
  unavailable: "AI օգնականը հիմա հասանելի չէ։ Փորձեք մեկ րոպեից։",
  // Set-up problems: the platform fixes them, the detail says what to fix.
  invalid_key: "AI օգնականի բանալին սխալ է։ Դիմեք Phay-ի թիմին։",
  no_credit: "AI ծառայության հաշվեկշիռը սպառվել է։ Դիմեք Phay-ի թիմին։",
  bad_model: "Ընտրված AI մոդելը հասանելի չէ։ Դիմեք Phay-ի թիմին։",
  forbidden: "AI ծառայությունը մերժեց հարցումը։ Դիմեք Phay-ի թիմին։",
  rate_limited: "Շատ հարցումներ են։ Փորձեք մեկ րոպեից։",
  network: "Սերվերը չի կարողանում կապվել AI ծառայության հետ։ Փորձեք մի փոքր ուշ։",
  bad_request: "AI ծառայությունը մերժեց հարցումը։ Դիմեք Phay-ի թիմին։",
};

/** **bold** inside a line of the model's answer. */
function inline(text: string): ReactNode {
  return text
    .split(/(\*\*[^*]+\*\*)/g)
    .map((part, i) =>
      part.startsWith("**") && part.endsWith("**") ? (
        <strong key={i}>{part.slice(2, -2)}</strong>
      ) : (
        <Fragment key={i}>{part}</Fragment>
      ),
    );
}

/** Paragraphs and simple "-"/"1." lists — enough for the assistant's plain answers. */
function Formatted({ text }: { text: string }) {
  return (
    <div className="space-y-2">
      {text.split(/\n{2,}/).map((block, i) => {
        const lines = block.split("\n").filter((line) => line.trim() !== "");
        const isList = lines.length > 0 && lines.every((line) => /^\s*([-*•]|\d+[.)])\s+/.test(line));
        if (isList) {
          const ordered = /^\s*\d/.test(lines[0]);
          const items = lines.map((line) => line.replace(/^\s*([-*•]|\d+[.)])\s+/, ""));
          const List = ordered ? "ol" : "ul";
          return (
            <List key={i} className={cn("space-y-1 pl-5", ordered ? "list-decimal" : "list-disc")}>
              {items.map((item, j) => (
                <li key={j}>{inline(item)}</li>
              ))}
            </List>
          );
        }
        return (
          <p key={i} className="whitespace-pre-line">
            {inline(block.replace(/^#+\s*/gm, ""))}
          </p>
        );
      })}
    </div>
  );
}

export function AssistantChat({
  suggestions = SUGGESTIONS.orders,
  configured,
  initialUsage,
  plans,
  canAsk,
  showDetails = false,
  initialConversations = [],
  initialConversation = null,
  agent = null,
  brandComplete = true,
}: {
  suggestions?: string[];
  configured: boolean;
  initialUsage: AssistantUsageDto;
  /** Plans with more questions, offered once they run out. */
  plans: AssistantPlanOfferDto[];
  canAsk: boolean;
  /** Platform staff (or a dev build) see the API's own error under the message. */
  showDetails?: boolean;
  /** The person's earlier chats, the latest first. */
  initialConversations?: AssistantConversationDto[];
  /** The chat opened by ?c= in the address. */
  initialConversation?: AssistantConversationDetailDto | null;
  /** The AI agent this chat is with; null: the general assistant. */
  agent?: AgentDto | null;
  /** The brand profile has what the agents need. */
  brandComplete?: boolean;
}) {
  const [messages, setMessages] = useState<Message[]>(() => initialConversation?.messages.map(toMessage) ?? []);
  const [conversationId, setConversationId] = useState<number | null>(initialConversation?.id ?? null);
  const [conversations, setConversations] = useState(initialConversations);
  const [loading, setLoading] = useState(false);
  const [draft, setDraft] = useState("");
  const [pending, setPending] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [usage, setUsage] = useState(initialUsage);
  const [offer, setOffer] = useState(plans);
  // Files in the question box: uploaded as soon as they are picked, sent by id.
  const [files, setFiles] = useState<ShownFile[]>([]);
  const [dragging, setDragging] = useState(false);
  const endRef = useRef<HTMLDivElement>(null);
  const pickerRef = useRef<HTMLInputElement>(null);

  useEffect(() => {
    endRef.current?.scrollIntoView({ behavior: "smooth", block: "end" });
  }, [messages, pending]);

  if (!configured) {
    return (
      <Card className="max-w-2xl">
        <p className="font-medium text-ink">AI օգնականը դեռ միացված չէ</p>
        <p className="mt-1 text-sm text-ink-muted">
          Պլատֆորմի ադմինիստրատորը պետք է ավելացնի AI ծառայության բանալին։ Մինչ այդ ձեր թվերը կարող եք տեսնել «Վերլուծություն»
          բաժնում։
        </p>
      </Card>
    );
  }

  const limitReached = usage.remaining !== null && usage.remaining <= 0;
  const uploading = files.some((f) => f.status === "uploading");
  const readyFiles = files.filter((f) => f.status === "ready" && f.attachment);

  function patchFile(key: string, patch: Partial<ShownFile>) {
    setFiles((list) => list.map((f) => (f.key === key ? { ...f, ...patch } : f)));
  }

  async function upload(key: string, file: File) {
    const body = new FormData();
    body.append("file", file);
    try {
      const response = await fetch("/api/admin/assistant/attachments", { method: "POST", body });
      const json = await response.json().catch(() => null);
      if (!response.ok) {
        if (json?.code === "limit_reached" && json.usage) setUsage(json.usage);
        const message = json?.errors?.file?.[0] ?? ERRORS[json?.code] ?? FILE_ERRORS.upload;
        patchFile(key, { status: "error", error: message });
        return;
      }
      const attachment = json.data as AssistantAttachmentDto;
      patchFile(key, { status: "ready", attachment });
    } catch {
      patchFile(key, { status: "error", error: FILE_ERRORS.upload });
    }
  }

  /** Picked, dropped or pasted files: checked here, then uploaded one by one. */
  function addFiles(picked: File[]) {
    if (picked.length === 0 || pending || limitReached) return;
    setError(null);
    const room = MAX_FILES - files.length;
    if (picked.length > room) setError(FILE_ERRORS.tooMany);
    for (const file of picked.slice(0, Math.max(0, room))) {
      const key = `local-${crypto.randomUUID()}`;
      const checked = checkFile(file);
      const shown: ShownFile = {
        key,
        name: file.name || "file",
        kind: "kind" in checked ? checked.kind : "text",
        size: file.size,
        src: "kind" in checked && checked.kind === "image" ? URL.createObjectURL(file) : null,
        attachment: null,
        status: "error" in checked ? "error" : "uploading",
        error: "error" in checked ? checked.error : undefined,
      };
      setFiles((list) => [...list, shown]);
      if (!("error" in checked)) upload(key, file);
    }
  }

  function removeFile(key: string) {
    const file = files.find((f) => f.key === key);
    if (!file) return;
    setFiles((list) => list.filter((f) => f.key !== key));
    if (file.src?.startsWith("blob:")) URL.revokeObjectURL(file.src);
    // Not sent yet: the API forgets it too (else it goes after a day).
    if (file.attachment) fetch(`/api/admin/assistant/attachments/${file.attachment.id}`, { method: "DELETE" }).catch(() => null);
  }

  function startNew() {
    setMessages([]);
    setConversationId(null);
    setError(null);
    rememberInUrl(null);
  }

  async function openConversation(id: number) {
    if (id === conversationId || pending) return;
    setLoading(true);
    setError(null);
    try {
      const response = await fetch(`/api/admin/assistant/conversations/${id}`);
      const json = await response.json().catch(() => null);
      if (!response.ok || !json?.data) {
        setError("Չհաջողվեց բացել զրույցը։ Փորձեք կրկին։");
        return;
      }
      const conversation = json.data as AssistantConversationDetailDto;
      setMessages(conversation.messages.map(toMessage));
      setConversationId(conversation.id);
      setDraft("");
      rememberInUrl(conversation.id);
    } catch {
      setError("Կապի սխալ։ Ստուգեք ինտերնետը և փորձեք կրկին։");
    } finally {
      setLoading(false);
    }
  }

  async function ask(question: string) {
    const text = question.trim();
    const sending = readyFiles;
    if ((!text && sending.length === 0) || pending || limitReached || uploading) return;

    const next: Message[] = [...messages, { role: "user", content: text, files: sending }];
    const kept = files;
    setMessages(next);
    setDraft("");
    setFiles([]);
    setError(null);
    setPending(true);

    try {
      const response = await fetch("/api/admin/assistant", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        // The earlier turns are kept on the server, in the conversation.
        body: JSON.stringify({
          question: text,
          conversation_id: conversationId,
          agent: agent?.key ?? null,
          attachments: sending.map((f) => f.attachment!.id),
        }),
      });
      const json = await response.json().catch(() => null);

      if (!response.ok) {
        // Give the question (and its files) back so it can be sent again.
        setMessages(messages);
        setDraft(text);
        setFiles(kept);
        if (json?.code === "limit_reached") {
          if (json.usage) setUsage(json.usage);
          if (json.offer?.plans) setOffer(json.offer.plans);
          return;
        }
        const message = ERRORS[json?.code] ?? "Չհաջողվեց ստանալ պատասխան։ Փորձեք կրկին։";
        setError(showDetails && json?.detail ? `${message} (${json.code}: ${json.detail})` : message);
        return;
      }

      const { reply, refused, conversation, attachments, ...nextUsage } = json.data as {
        reply: string;
        refused: boolean;
        conversation: AssistantConversationDto | null;
        attachments?: AssistantAttachmentDto[];
      } & AssistantUsageDto;
      // The question shows its kept files (served by the API, no local blobs).
      const asked: Message = { role: "user", content: text, files: (attachments ?? []).map(shownFromAttachment) };
      for (const f of sending) if (f.src?.startsWith("blob:")) URL.revokeObjectURL(f.src);
      setMessages([...messages, asked, toMessage({ role: "assistant", content: reply, refused })]);
      setUsage(nextUsage);
      if (conversation) {
        // The asked chat goes to the top of the history.
        setConversations((list) => [conversation, ...list.filter((c) => c.id !== conversation.id)]);
        if (conversation.id !== conversationId) {
          setConversationId(conversation.id);
          rememberInUrl(conversation.id);
        }
      }
    } catch {
      setMessages(messages);
      setDraft(text);
      setFiles(kept);
      setError("Կապի սխալ։ Ստուգեք ինտերնետը և փորձեք կրկին։");
    } finally {
      setPending(false);
    }
  }

  return (
    <div className="grid items-start gap-4 lg:grid-cols-[18rem_minmax(0,48rem)] lg:gap-6">
      <AssistantHistory
        conversations={conversations}
        activeId={conversationId}
        busy={pending || loading}
        onOpen={openConversation}
        onNew={startNew}
        onRenamed={(renamed) => setConversations((list) => list.map((c) => (c.id === renamed.id ? renamed : c)))}
        onDeleted={(id) => {
          setConversations((list) => list.filter((c) => c.id !== id));
          if (id === conversationId) startNew();
        }}
      />
      <div className="flex min-w-0 flex-col gap-4">
        <AssistantQuotaBar usage={usage} />
        <Card className="min-h-[24rem] space-y-4">
          {agent ? <AgentHeader agent={agent} talking={pending} /> : null}
          {loading ? (
            <p className="text-sm text-ink-muted">Բացվում է…</p>
          ) : messages.length === 0 ? (
            agent ? (
              <div className="space-y-4">
                <AgentBubble agent={agent}>
                  <p>{agent.greeting}</p>
                </AgentBubble>
                <div className="grid gap-2 sm:grid-cols-2">
                  {(agent.needsBrand && !brandComplete
                    ? [GET_ACQUAINTED, ...agent.suggestions.slice(0, 3)]
                    : agent.suggestions
                  ).map((suggestion) => (
                    <button
                      key={suggestion}
                      type="button"
                      onClick={() => ask(suggestion)}
                      disabled={limitReached}
                      style={agentTintStyle(agent.key)}
                      className={cn(
                        "rounded-md border px-3 py-2 text-left text-sm text-ink transition-colors disabled:opacity-50",
                        suggestion === GET_ACQUAINTED
                          ? "border-[var(--tint)] bg-[var(--tint-bg)] font-medium"
                          : "border-border hover:border-[var(--tint)] hover:bg-[var(--tint-bg)]",
                      )}
                    >
                      {suggestion}
                    </button>
                  ))}
                </div>
              </div>
            ) : (
              <div className="space-y-4">
                <div className="flex items-center gap-2 text-ink-muted">
                  <Sparkles className="h-4 w-4 text-primary" />
                  <p className="text-sm">Սկսեք հարցով կամ ընտրեք օրինակներից մեկը։</p>
                </div>
                <div className="grid gap-2 sm:grid-cols-2">
                  {suggestions.map((suggestion) => (
                    <button
                      key={suggestion}
                      type="button"
                      onClick={() => ask(suggestion)}
                      disabled={limitReached}
                      className="rounded-md border border-border px-3 py-2 text-left text-sm text-ink hover:border-primary hover:bg-primary-light disabled:opacity-50"
                    >
                      {suggestion}
                    </button>
                  ))}
                </div>
              </div>
            )
          ) : (
            messages.map((message, i) =>
              message.role === "assistant" && agent ? (
                <AgentBubble key={i} agent={agent}>
                  <Formatted text={message.content} />
                </AgentBubble>
              ) : (
                <div key={i} className={cn("flex", message.role === "user" ? "justify-end" : "justify-start")}>
                  <div
                    className={cn(
                      "max-w-[85%] rounded-lg px-4 py-3 text-sm",
                      message.role === "user" ? "bg-primary text-primary-foreground" : "bg-surface-secondary text-ink",
                    )}
                  >
                    {message.role === "user" ? (
                      <div className="space-y-2">
                        <MessageFiles files={message.files ?? []} canSaveToBrand={agent !== null} />
                        {message.content ? <p className="whitespace-pre-line">{message.content}</p> : null}
                      </div>
                    ) : (
                      <Formatted text={message.content} />
                    )}
                  </div>
                </div>
              ),
            )
          )}
          {pending ? (
            agent ? (
              <p className="flex items-center gap-2 text-sm text-ink-muted" style={agentTintStyle(agent.key)}>
                <span className={cn(agentStyles.dots, "text-[var(--tint)]")} aria-hidden="true">
                  <span />
                  <span />
                  <span />
                </span>
                {agent.persona.hy} · պատրաստում է պատասխանը…
              </p>
            ) : (
              <p className="text-sm text-ink-muted">Օգնականը վերլուծում է ձեր տվյալները…</p>
            )
          ) : null}
          <div ref={endRef} />
        </Card>

        {error ? <p className="text-sm text-danger">{error}</p> : null}
        {limitReached ? <AssistantLimitReached usage={usage} plans={offer} canAsk={canAsk} /> : null}

        {/* On a phone the question box stays at the bottom of the screen while the answer scrolls. */}
        <form
          className={cn(
            "sticky bottom-0 z-10 -mx-4 space-y-2 border-t border-border bg-background/95 px-4 pt-2 backdrop-blur sm:static sm:mx-0 sm:border-0 sm:bg-transparent sm:p-0 sm:backdrop-blur-none",
            dragging && "rounded-md outline-2 outline-offset-4 outline-primary outline-dashed",
          )}
          style={{ paddingBottom: "max(0.5rem, env(safe-area-inset-bottom, 0px))" }}
          onSubmit={(e) => {
            e.preventDefault();
            ask(draft);
          }}
          // Files dropped on the question box are attached.
          onDragOver={(e) => {
            if (!e.dataTransfer.types.includes("Files")) return;
            e.preventDefault();
            setDragging(true);
          }}
          onDragLeave={() => setDragging(false)}
          onDrop={(e) => {
            if (e.dataTransfer.files.length === 0) return;
            e.preventDefault();
            setDragging(false);
            addFiles(Array.from(e.dataTransfer.files));
          }}
        >
          <PendingFiles files={files} onRemove={removeFile} />
          <div className="flex items-end gap-2">
            <input
              ref={pickerRef}
              type="file"
              accept={ACCEPT}
              multiple
              hidden
              onChange={(e) => {
                addFiles(Array.from(e.target.files ?? []));
                e.target.value = "";
              }}
            />
            <Button
              type="button"
              variant="secondary"
              className="h-12 w-12 shrink-0 px-0! sm:h-10 sm:w-10"
              disabled={pending || limitReached || files.length >= MAX_FILES}
              onClick={() => pickerRef.current?.click()}
              aria-label="Կցել ֆայլ"
              title="Կցել նկար, PDF կամ տեքստային ֆայլ (մինչև 4)"
            >
              <Paperclip className="h-4 w-4" />
            </Button>
            <Textarea
              value={draft}
              onChange={(e) => setDraft(e.target.value)}
              onKeyDown={(e) => {
                if (e.key === "Enter" && !e.shiftKey) {
                  e.preventDefault();
                  ask(draft);
                }
              }}
              // A screenshot or picture pasted into the box is attached.
              onPaste={(e) => {
                const pasted = Array.from(e.clipboardData.files);
                if (pasted.length === 0) return;
                e.preventDefault();
                addFiles(pasted);
              }}
              rows={2}
              maxLength={4000}
              enterKeyHint="send"
              placeholder={
                limitReached
                  ? ERRORS.limit_reached
                  : files.length > 0
                    ? "Ի՞նչ անել այս ֆայլերի հետ…"
                    : agent
                      ? `Գրեք ${agent.persona.hy}ին…`
                      : "Գրեք ձեր հարցը…"
              }
              disabled={pending || limitReached}
            />
            <Button
              type="submit"
              className="h-12 w-12 shrink-0 px-0 sm:h-10 sm:w-auto sm:px-4"
              disabled={pending || limitReached || uploading || (draft.trim() === "" && readyFiles.length === 0)}
              aria-label="Ուղարկել"
            >
              <Send className="h-4 w-4" />
            </Button>
          </div>
        </form>

        <div className="flex items-center justify-end text-xs text-ink-faint">
          {messages.length > 0 ? (
            <button type="button" className="hover:text-ink" onClick={startNew} disabled={pending}>
              Նոր զրույց
            </button>
          ) : null}
        </div>
      </div>
    </div>
  );
}

/** Who the chat is with: the agent, moving, with its role. */
function AgentHeader({ agent, talking }: { agent: AgentDto; talking: boolean }) {
  return (
    <div className="-mx-4 -mt-4 mb-2 flex items-center gap-3 border-b border-border px-4 py-3 sm:-mx-5 sm:-mt-5 sm:px-5" style={agentTintStyle(agent.key)}>
      <AgentAvatar agentKey={agent.key} size={48} talking={talking} />
      <div className="min-w-0">
        <p className="text-sm font-semibold text-ink">
          {agent.persona.hy} <span className="font-normal text-[var(--tint)]">· {agent.role.hy}</span>
        </p>
        <p className="truncate text-xs text-ink-muted">{agent.tagline}</p>
      </div>
    </div>
  );
}

/** An agent's words, with its face next to them. */
function AgentBubble({ agent, children }: { agent: AgentDto; children: ReactNode }) {
  return (
    <div className="flex items-end gap-2" style={agentTintStyle(agent.key)}>
      <AgentAvatar agentKey={agent.key} size={34} className="shrink-0" />
      <div className="max-w-[85%] rounded-lg rounded-bl-sm border border-[var(--tint-soft)] bg-[var(--tint-bg)] px-4 py-3 text-sm text-ink">
        {children}
      </div>
    </div>
  );
}
