"use client";

import { useRouter } from "next/navigation";
import { useState } from "react";
import { ExternalLink, Megaphone, Plus, Trash2 } from "lucide-react";
import type { SocialComparisonRowDto, SocialCompetitorDto } from "@/lib/adminApi";
import { Badge, Button, Card, cn, Input, TableWrap, Td, Th, Thead, Tr } from "@/ui";
import { ConfirmDialog } from "@/ui/dialog/ConfirmDialog";
import { formatNumber } from "../_components/tables/shared";

const ADD_ERRORS: Record<string, string> = {
  competitor_invalid: "Սա Instagram-ի անուն չէ։ Գրեք, օրինակ, cleanhome.am կամ հղումը պրոֆիլին։",
  competitor_exists: "Այս հաշիվն արդեն ցանկում է։",
  competitor_limit: "Կարելի է հետևել առավելագույնը 10 հաշվի։",
  competitor_own: "Սա ձեր սեփական հաշիվն է։",
};

const STATUS: Record<SocialCompetitorDto["status"], { label: string; tone: "default" | "success" | "warning" | "danger" }> = {
  ok: { label: "Թարմ", tone: "success" },
  pending: { label: "Սպասում է", tone: "warning" },
  not_found: { label: "Չի գտնվել", tone: "danger" },
  error: { label: "Սխալ", tone: "danger" },
};

const number = (value: number | null | undefined) => (value === null || value === undefined ? "—" : formatNumber(value));

/**
 * Competitors' public Instagram figures next to the business's own: added
 * by username, read through the business's Instagram (Business Discovery).
 * Only business and creator accounts can be read, and only what they show
 * to everyone.
 */
export function Competitors({
  competitors,
  comparison,
  max,
  canManage,
  hasInstagram,
}: {
  competitors: SocialCompetitorDto[];
  comparison: SocialComparisonRowDto[];
  max: number;
  canManage: boolean;
  hasInstagram: boolean;
}) {
  const router = useRouter();
  const [username, setUsername] = useState("");
  const [pending, setPending] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [removing, setRemoving] = useState<SocialCompetitorDto | null>(null);

  async function add(event: React.FormEvent) {
    event.preventDefault();
    if (!username.trim()) return;
    setPending(true);
    setError(null);
    try {
      const response = await fetch("/api/admin/social/competitors", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ username }),
      });
      const json = await response.json().catch(() => null);
      if (!response.ok) {
        setError(ADD_ERRORS[json?.code] ?? "Չհաջողվեց ավելացնել։ Փորձեք կրկին։");
        return;
      }
      setUsername("");
      if (json?.data?.status === "not_found") {
        setError(`«${json.data.username}» հաշիվը չգտնվեց կամ այն պրոֆեսիոնալ չէ (անձնական հաշիվները չեն կարդացվում)։`);
      }
      router.refresh();
    } catch {
      setError("Չհաջողվեց ավելացնել։ Փորձեք կրկին։");
    } finally {
      setPending(false);
    }
  }

  async function remove() {
    if (!removing) return;
    setPending(true);
    try {
      await fetch(`/api/admin/social/competitors/${removing.id}`, { method: "DELETE" });
      setRemoving(null);
      router.refresh();
    } finally {
      setPending(false);
    }
  }

  return (
    <section className="space-y-4 border-t border-border pt-8">
      <div>
        <h2 className="text-lg font-semibold text-ink">Մրցակիցներ</h2>
        <p className="mt-1 max-w-2xl text-sm text-ink-muted">
          Ավելացրեք մրցակիցների Instagram հաշիվները և համեմատեք՝ հետևորդներ, որքան հաճախ են հրապարակում, միջին հավանումներ և
          ներգրավվածություն։ Միայն այն, ինչ բոլորն են տեսնում. ուրիշի ընդգրկումն ու վիճակագրությունը հասանելի չեն։
        </p>
      </div>

      {!hasInstagram ? (
        <p className="rounded-md bg-warning-light px-3 py-2 text-sm text-warning">
          Մրցակիցներին կարդում ենք ձեր Instagram պրոֆեսիոնալ հաշվի միջոցով. միացրեք և ընտրեք այն վերևում։
        </p>
      ) : null}

      {canManage ? (
        <form onSubmit={add} className="flex flex-col gap-2 sm:flex-row">
          <Input
            value={username}
            onChange={(e) => setUsername(e.target.value)}
            placeholder="Instagram անուն կամ հղում, օր.՝ cleanhome.am"
            aria-label="Մրցակցի Instagram հաշիվը"
            className="sm:max-w-sm"
            disabled={pending || competitors.length >= max}
          />
          <Button type="submit" disabled={pending || !username.trim() || competitors.length >= max}>
            <Plus className="h-4 w-4" />
            {pending ? "Մի պահ…" : "Ավելացնել"}
          </Button>
          <span className="self-center text-xs text-ink-faint">
            {competitors.length} / {max}
          </span>
        </form>
      ) : null}
      {error ? <p className="text-sm text-danger">{error}</p> : null}

      {comparison.length > 1 ? (
        <TableWrap>
          <Thead>
            <Th>Հաշիվ</Th>
            <Th className="text-right">Հետևորդներ</Th>
            <Th className="text-right">Գրառում/շաբաթ</Th>
            <Th className="text-right">Միջ. հավանում</Th>
            <Th className="text-right">Միջ. մեկնաբանություն</Th>
            <Th className="text-right">Ներգրավվածություն</Th>
            <Th>Վերջին գրառումը</Th>
          </Thead>
          <tbody>
            {comparison.map((row) => (
              <Tr key={`${row.own}-${row.username}`} className={cn(row.own && "bg-primary-light/40")}>
                <Td className="font-medium">
                  @{row.username}
                  {row.own ? <span className="ml-1.5 text-xs font-normal text-primary">(դուք)</span> : null}
                </Td>
                <Td className="whitespace-nowrap text-right tabular-nums">{number(row.followers)}</Td>
                <Td className="whitespace-nowrap text-right tabular-nums">{number(row.postsPerWeek)}</Td>
                <Td className="whitespace-nowrap text-right tabular-nums">{row.hiddenLikes ? "թաքցված" : number(row.avgLikes)}</Td>
                <Td className="whitespace-nowrap text-right tabular-nums">{number(row.avgComments)}</Td>
                <Td className="whitespace-nowrap text-right tabular-nums">{row.engagementRate !== null ? `${formatNumber(row.engagementRate)}%` : "—"}</Td>
                <Td className="whitespace-nowrap text-ink-muted">{row.lastPostAt ? row.lastPostAt.slice(0, 10).split("-").reverse().join(".") : "—"}</Td>
              </Tr>
            ))}
          </tbody>
        </TableWrap>
      ) : null}

      {competitors.length > 0 ? (
        <div className="grid gap-3 md:grid-cols-2">
          {competitors.map((c) => (
            <Card key={c.id} className="space-y-2">
              <div className="flex items-start justify-between gap-2">
                <div className="min-w-0">
                  <p className="truncate text-sm font-semibold text-ink">@{c.username}</p>
                  {c.name ? <p className="truncate text-xs text-ink-muted">{c.name}</p> : null}
                </div>
                <Badge tone={STATUS[c.status].tone}>{STATUS[c.status].label}</Badge>
              </div>
              {c.status === "ok" ? (
                <p className="text-sm text-ink">
                  {number(c.followers)} հետևորդ
                  {c.followersChange30Days !== undefined ? (
                    <span className={cn("ml-1.5 text-xs", c.followersChange30Days >= 0 ? "text-success" : "text-danger")}>
                      {c.followersChange30Days > 0 ? "+" : ""}
                      {formatNumber(c.followersChange30Days)} 30 օրում
                    </span>
                  ) : null}
                  {" · "}
                  {number(c.mediaCount)} գրառում
                </p>
              ) : c.status === "not_found" ? (
                <p className="text-xs text-ink-muted">Հաշիվը չգտնվեց կամ պրոֆեսիոնալ չէ։</p>
              ) : c.status === "error" && c.error === "needs_instagram" ? (
                <p className="text-xs text-ink-muted">Կկարդանք, երբ միացնեք ձեր Instagram-ը։</p>
              ) : c.status === "pending" ? (
                <p className="text-xs text-ink-muted">Կկարդանք հաջորդ թարմացման ժամանակ։</p>
              ) : (
                <p className="text-xs text-ink-muted">Meta-ն չպատասխանեց, կփորձենք նորից։</p>
              )}
              <div className="flex flex-wrap items-center gap-3 text-xs">
                <a href={c.url} target="_blank" rel="noreferrer" className="inline-flex items-center gap-1 text-primary hover:underline">
                  <ExternalLink className="h-3.5 w-3.5" />
                  Պրոֆիլ
                </a>
                <a href={c.adLibraryUrl} target="_blank" rel="noreferrer" className="inline-flex items-center gap-1 text-primary hover:underline">
                  <Megaphone className="h-3.5 w-3.5" />
                  Գովազդները
                </a>
                {c.syncedAt ? <span className="text-ink-faint">{c.syncedAt}</span> : null}
                {canManage ? (
                  <button
                    type="button"
                    onClick={() => setRemoving(c)}
                    className="ml-auto inline-flex items-center gap-1 text-ink-faint hover:text-danger"
                  >
                    <Trash2 className="h-3.5 w-3.5" />
                    Հեռացնել
                  </button>
                ) : null}
              </div>
            </Card>
          ))}
        </div>
      ) : null}

      <p className="text-xs text-ink-faint">
        «Գովազդները» բացում է Meta-ի հրապարակային գովազդների գրադարանը։ Մրցակիցների թվերը թարմանում են օրը երկու անգամ։
      </p>

      {removing ? (
        <ConfirmDialog
          open
          pending={pending}
          onCancel={() => setRemoving(null)}
          onConfirm={remove}
          content={{
            title: `Հեռացնե՞լ @${removing.username}-ը`,
            description: "Նրա թվերն այլևս չեն թարմանա և չեն երևա համեմատության մեջ։",
            confirmLabel: "Հեռացնել",
            tone: "danger",
          }}
        />
      ) : null}
    </section>
  );
}
