"use client";

import { Fragment, useEffect, useRef, useState, type ReactNode } from "react";
import { Send, Sparkles } from "lucide-react";
import type {
  AgentDto,
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
import { SUGGESTIONS } from "./suggestions";

type Message = { role: "user" | "assistant"; content: string };

const REFUSED = "Այս հարցին չեմ կարող պատասխանել։ Փորձեք ձևակերպել այլ կերպ։";

/** A kept question or answer as the chat shows it. */
function toMessage(message: Pick<AssistantMessageDto, "role" | "content" | "refused">): Message {
  if (message.role === "user") return { role: "user", content: message.content };
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
  const endRef = useRef<HTMLDivElement>(null);

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
    if (!text || pending || limitReached) return;

    const next: Message[] = [...messages, { role: "user", content: text }];
    setMessages(next);
    setDraft("");
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
        }),
      });
      const json = await response.json().catch(() => null);

      if (!response.ok) {
        // Give the question back so it can be sent again.
        setMessages(messages);
        setDraft(text);
        if (json?.code === "limit_reached") {
          if (json.usage) setUsage(json.usage);
          if (json.offer?.plans) setOffer(json.offer.plans);
          return;
        }
        const message = ERRORS[json?.code] ?? "Չհաջողվեց ստանալ պատասխան։ Փորձեք կրկին։";
        setError(showDetails && json?.detail ? `${message} (${json.code}: ${json.detail})` : message);
        return;
      }

      const { reply, refused, conversation, ...nextUsage } = json.data as {
        reply: string;
        refused: boolean;
        conversation: AssistantConversationDto | null;
      } & AssistantUsageDto;
      setMessages([...next, toMessage({ role: "assistant", content: reply, refused })]);
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
                      <p className="whitespace-pre-line">{message.content}</p>
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

        <form
          className="flex items-end gap-2"
          onSubmit={(e) => {
            e.preventDefault();
            ask(draft);
          }}
        >
          <Textarea
            value={draft}
            onChange={(e) => setDraft(e.target.value)}
            onKeyDown={(e) => {
              if (e.key === "Enter" && !e.shiftKey) {
                e.preventDefault();
                ask(draft);
              }
            }}
            rows={2}
            maxLength={4000}
            placeholder={limitReached ? ERRORS.limit_reached : agent ? `Գրեք ${agent.persona.hy}ին…` : "Գրեք ձեր հարցը…"}
            disabled={pending || limitReached}
          />
          <Button type="submit" disabled={pending || limitReached || draft.trim() === ""} aria-label="Ուղարկել">
            <Send className="h-4 w-4" />
          </Button>
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
    <div className="-mx-5 -mt-5 mb-2 flex items-center gap-3 border-b border-border px-5 py-3" style={agentTintStyle(agent.key)}>
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
