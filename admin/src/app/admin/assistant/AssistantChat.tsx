"use client";

import { Fragment, useEffect, useRef, useState, type ReactNode } from "react";
import { Send, Sparkles } from "lucide-react";
import { Button, Card, cn, Textarea } from "../_components/ui";
import { SUGGESTIONS } from "./suggestions";

type Message = { role: "user" | "assistant"; content: string };

const ERRORS: Record<string, string> = {
  not_configured: "AI օգնականը դեռ միացված չէ։",
  limit_reached: "Այս ամսվա հարցերի սահմանաչափը սպառվել է։",
  unavailable: "AI օգնականը հիմա հասանելի չէ։ Փորձեք մեկ րոպեից։",
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
  initialUsed,
  limit,
}: {
  suggestions?: string[];
  configured: boolean;
  initialUsed: number;
  limit: number;
}) {
  const [messages, setMessages] = useState<Message[]>([]);
  const [draft, setDraft] = useState("");
  const [pending, setPending] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [used, setUsed] = useState(initialUsed);
  const endRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    endRef.current?.scrollIntoView({ behavior: "smooth", block: "end" });
  }, [messages, pending]);

  if (!configured) {
    return (
      <Card className="max-w-2xl">
        <p className="font-medium text-ink">AI օգնականը դեռ միացված չէ</p>
        <p className="mt-1 text-sm text-ink-muted">
          Պլատֆորմի ադմինիստրատորը պետք է ավելացնի AI ծառայության բանալին։ Մինչ այդ ձեր թվերը կարող եք տեսնել
          «Վերլուծություն» բաժնում։
        </p>
      </Card>
    );
  }

  const limitReached = used >= limit;

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
        body: JSON.stringify({ messages: next }),
      });
      const json = await response.json().catch(() => null);

      if (!response.ok) {
        // Give the question back so it can be sent again.
        setMessages(messages);
        setDraft(text);
        setError(ERRORS[json?.code] ?? "Չհաջողվեց ստանալ պատասխան։ Փորձեք կրկին։");
        if (json?.code === "limit_reached") setUsed(limit);
        return;
      }

      const reply: string = json.data.refused
        ? "Այս հարցին չեմ կարող պատասխանել։ Փորձեք ձևակերպել այլ կերպ։"
        : json.data.reply || "Պատասխանը դատարկ է։ Փորձեք կրկին։";
      setMessages([...next, { role: "assistant", content: reply }]);
      setUsed(json.data.used ?? used + 1);
    } catch {
      setMessages(messages);
      setDraft(text);
      setError("Կապի սխալ։ Ստուգեք ինտերնետը և փորձեք կրկին։");
    } finally {
      setPending(false);
    }
  }

  return (
    <div className="flex max-w-3xl flex-col gap-4">
      <Card className="min-h-[24rem] space-y-4">
        {messages.length === 0 ? (
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
        ) : (
          messages.map((message, i) => (
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
          ))
        )}
        {pending ? <p className="text-sm text-ink-muted">Օգնականը վերլուծում է ձեր տվյալները…</p> : null}
        <div ref={endRef} />
      </Card>

      {error ? <p className="text-sm text-danger">{error}</p> : null}

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
          placeholder={limitReached ? ERRORS.limit_reached : "Գրեք ձեր հարցը…"}
          disabled={pending || limitReached}
        />
        <Button type="submit" disabled={pending || limitReached || draft.trim() === ""} aria-label="Ուղարկել">
          <Send className="h-4 w-4" />
        </Button>
      </form>

      <div className="flex items-center justify-between text-xs text-ink-faint">
        <span>
          Այս ամիս՝ {used} / {limit} հարց
        </span>
        {messages.length > 0 ? (
          <button
            type="button"
            className="hover:text-ink"
            onClick={() => {
              setMessages([]);
              setError(null);
            }}
          >
            Նոր զրույց
          </button>
        ) : null}
      </div>
    </div>
  );
}
