"use client";

// A change of the team the HR agent prepared in the chat (the API's
// HrChanges): an absence, an employee, a job opening, a candidate, a hire.
// The card lists what changes; nothing is written until the owner presses
// «Կիրառել», and an applied change can be undone while nobody touched it.
import { useEffect, useState } from "react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { Undo2, UsersRound } from "lucide-react";
import type { HrChangeDto } from "@/lib/adminApi";
import { friendlyApiError, readApiError } from "@/lib/apiError";
import { Badge, Button, cn, type BadgeTone } from "@/ui";
import { ConfirmDialog } from "@/ui/dialog/ConfirmDialog";

async function failure(response: Response | null): Promise<string> {
  if (!response) return "Սերվերի հետ կապ չկա։ Փորձեք նորից։";
  const error = await readApiError(response);
  return error.code === "change_refused" ? error.message : friendlyApiError(error);
}

const STATUS: Record<HrChangeDto["status"], { label: string; tone: BadgeTone }> = {
  planned: { label: "Սպասում է հաստատման", tone: "pending" },
  applied: { label: "Կիրառված է", tone: "success" },
  undone: { label: "Հետ է բերվել", tone: "default" },
  discarded: { label: "Չեղարկված է", tone: "default" },
};

export function HrCard({ id }: { id: number }) {
  const router = useRouter();
  const [change, setChange] = useState<HrChangeDto | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [asking, setAsking] = useState<"apply" | "undo" | null>(null);
  const [pending, setPending] = useState(false);

  useEffect(() => {
    let cancelled = false;
    fetch(`/api/admin/hr-changes/${id}`)
      .catch(() => null)
      .then(async (response) => {
        if (cancelled) return;
        if (response?.ok) setChange(((await response.json()) as { data: HrChangeDto }).data);
        else setError(await failure(response));
      });
    return () => {
      cancelled = true;
    };
  }, [id]);

  async function act(action: "apply" | "undo" | "discard") {
    setPending(true);
    setError(null);
    const response = await fetch(`/api/admin/hr-changes/${id}/${action}`, { method: "POST" }).catch(() => null);
    setPending(false);
    setAsking(null);
    if (!response?.ok) {
      setError(await failure(response));
      return;
    }
    setChange(((await response.json()) as { data: HrChangeDto }).data);
    router.refresh();
  }

  const status = change ? STATUS[change.status] : null;

  return (
    <div className="mt-2 space-y-3 rounded-xl border border-border bg-surface p-3 sm:p-4">
      <div className="flex items-start gap-3">
        <span
          className={cn(
            "flex h-9 w-9 shrink-0 items-center justify-center rounded-lg",
            change?.deletes ? "bg-danger-light text-danger" : "bg-primary-light text-primary",
          )}
        >
          <UsersRound className="h-[18px] w-[18px]" strokeWidth={1.8} />
        </span>
        <div className="min-w-0 flex-1">
          <p className="text-sm font-semibold break-words text-ink">{change?.title ?? "Թիմի փոփոխություն"}</p>
          {status ? (
            <Badge tone={status.tone} className="mt-1">
              {status.label}
            </Badge>
          ) : null}
        </div>
      </div>

      {!change && !error ? <p className="text-sm text-ink-muted">Բեռնվում է…</p> : null}

      {change && change.lines.length > 0 ? (
        <ul className="space-y-1 text-sm text-ink">
          {change.lines.map((line, i) => (
            <li key={i} className={cn("flex gap-2", line.startsWith("Ուշադրություն") && "text-warning")}>
              <span className={cn("mt-2 h-1.5 w-1.5 shrink-0 rounded-full", change.deletes ? "bg-danger" : "bg-primary")} />
              <span className="min-w-0 break-words whitespace-pre-line">{line}</span>
            </li>
          ))}
        </ul>
      ) : null}

      {change?.stale && change.status === "planned" ? <p className="text-sm text-warning">{change.stale}</p> : null}
      {error ? <p className="text-sm whitespace-pre-line text-danger">{error}</p> : null}

      {change?.status === "planned" && !change.stale ? (
        <div className="flex flex-wrap items-center gap-2">
          <Button size="sm" variant={change.deletes ? "danger" : "primary"} onClick={() => setAsking("apply")}>
            Կիրառել
          </Button>
          <Button size="sm" variant="ghost" disabled={pending} onClick={() => act("discard")}>
            Չեղարկել
          </Button>
          <span className="text-xs text-ink-muted">Մինչև սեղմելը ոչինչ չի փոխվում։</span>
        </div>
      ) : null}

      {change?.status === "applied" ? (
        <div className="flex flex-wrap items-center gap-2">
          <Link href={change.link} className="text-sm font-medium text-primary hover:underline">
            Բացել
          </Link>
          {change.undoBlocked ? (
            <span className="text-xs text-ink-muted">Հետ բերել այլևս հնարավոր չէ․ {change.undoBlocked}</span>
          ) : (
            <Button size="sm" variant="secondary" onClick={() => setAsking("undo")}>
              <Undo2 className="h-4 w-4" />
              Հետ բերել
            </Button>
          )}
        </div>
      ) : null}

      {change ? (
        <ConfirmDialog
          open={asking !== null}
          pending={pending}
          onCancel={() => setAsking(null)}
          onConfirm={() => act(asking ?? "apply")}
          content={
            asking === "undo"
              ? {
                  title: "Հետ բերե՞լ փոփոխությունը",
                  description: "Ամեն ինչ կվերադառնա այնպես, ինչպես էր մինչև այս փոփոխությունը։",
                  confirmLabel: "Հետ բերել",
                }
              : {
                  title: `${change.title}?`,
                  description: "Փոփոխությունը կկատարվի ճիշտ այնպես, ինչպես ցույց է տրված։ Հետո կարող եք հետ բերել։",
                  confirmLabel: "Կիրառել",
                  tone: change.deletes ? "danger" : "primary",
                }
          }
        />
      ) : null}
    </div>
  );
}
