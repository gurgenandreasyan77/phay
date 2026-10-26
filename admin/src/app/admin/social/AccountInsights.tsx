import { ExternalLink } from "lucide-react";
import type { SocialAccountInsightsDto, SocialPostDto } from "@/lib/adminApi";
import { Card, cn } from "@/ui";
import { HBars } from "../_components/analytics/Charts";
import { formatNumber } from "../_components/tables/shared";
import { GENDERS, METRICS, NETWORKS, POST_KINDS, WEEKDAYS } from "./labels";

function Change({ value, percent }: { value: number | undefined; percent?: boolean }) {
  if (value === undefined || value === null) return null;
  return (
    <span className={cn("text-xs font-medium", value >= 0 ? "text-success" : "text-danger")}>
      {value > 0 ? "+" : ""}
      {formatNumber(value)}
      {percent ? "%" : ""}
    </span>
  );
}

function Tile({ label, value, change, hint }: { label: string; value: string; change?: React.ReactNode; hint?: string }) {
  return (
    <div className="space-y-1 rounded-lg border border-border bg-surface p-3 sm:p-4">
      <p className="text-xs text-ink-muted">{label}</p>
      <p className="text-xl font-semibold tabular-nums text-ink sm:text-2xl">{value}</p>
      <p className="flex flex-wrap items-baseline gap-1.5 text-xs text-ink-faint">
        {change}
        {hint ? <span>{hint}</span> : null}
      </p>
    </div>
  );
}

function dayMonth(date: string | null | undefined): string {
  return date ? `${date.slice(8, 10)}.${date.slice(5, 7)}` : "";
}

/**
 * One connected account: followers and their growth, the last 28 days
 * against the 28 before, who follows it, how its posts do, and the best
 * posts. All from the last sync (Meta updates insights about daily).
 */
export function AccountInsights({ account }: { account: SocialAccountInsightsDto }) {
  const network = NETWORKS[account.network] ?? { label: account.network, color: "var(--color-primary)" };
  const period = account.last28Days;
  const totals = period ? Object.keys(METRICS).filter((key) => period.totals[key] !== undefined) : [];
  const stats = account.postsLast90Days;
  const audience = account.followersAudience;

  return (
    <section className="space-y-4">
      <div className="flex flex-wrap items-center gap-3">
        {account.picture ? (
          // eslint-disable-next-line @next/next/no-img-element -- Meta's CDN, its URLs change
          <img src={account.picture} alt="" className="h-10 w-10 rounded-full border border-border object-cover" />
        ) : (
          <span className="h-10 w-10 rounded-full" style={{ backgroundColor: network.color, opacity: 0.2 }} />
        )}
        <div className="min-w-0">
          <p className="text-xs font-semibold" style={{ color: network.color }}>
            {network.label}
          </p>
          <p className="flex items-center gap-1.5 text-base font-semibold text-ink">
            {account.handle}
            {account.url ? (
              <a
                href={account.url}
                target="_blank"
                rel="noreferrer"
                className="text-ink-faint hover:text-primary"
                aria-label="Բացել"
              >
                <ExternalLink className="h-3.5 w-3.5" />
              </a>
            ) : null}
          </p>
        </div>
      </div>

      <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
        <Tile
          label="Հետևորդներ"
          value={account.followers !== undefined ? formatNumber(account.followers) : "—"}
          change={<Change value={account.followersChange30Days} />}
          hint={account.followersChange30Days !== undefined ? "30 օրում" : "աճը կերևա մի քանի օրից"}
        />
        {totals.slice(0, 7).map((key) => (
          <Tile
            key={key}
            label={METRICS[key]}
            value={formatNumber(period!.totals[key])}
            change={<Change value={period!.changePercent[key]} percent />}
            hint={`${dayMonth(period!.from)}–${dayMonth(period!.to)}`}
          />
        ))}
      </div>

      <div className={cn("grid gap-4", account.network === "instagram" ? "lg:grid-cols-3" : "lg:grid-cols-2")}>
        <Card className="space-y-3">
          <p className="text-sm font-semibold text-ink">Գրառումները 90 օրում</p>
          {stats.posts === 0 ? (
            <p className="text-sm text-ink-muted">Վերջին 90 օրում գրառումներ չկան։</p>
          ) : (
            <dl className="grid grid-cols-2 gap-x-4 gap-y-2 text-sm">
              <dt className="text-ink-muted">Գրառումներ</dt>
              <dd className="text-right tabular-nums text-ink">{stats.posts}</dd>
              <dt className="text-ink-muted">Շաբաթական</dt>
              <dd className="text-right tabular-nums text-ink">{formatNumber(stats.postsPerWeek ?? 0)}</dd>
              <dt className="text-ink-muted">Միջ. հավանում</dt>
              <dd className="text-right tabular-nums text-ink">
                {stats.avgLikes !== undefined ? formatNumber(stats.avgLikes) : "—"}
              </dd>
              <dt className="text-ink-muted">Միջ. մեկնաբանություն</dt>
              <dd className="text-right tabular-nums text-ink">
                {stats.avgComments !== undefined ? formatNumber(stats.avgComments) : "—"}
              </dd>
              {stats.avgReach !== undefined ? (
                <>
                  <dt className="text-ink-muted">Միջ. ընդգրկում</dt>
                  <dd className="text-right tabular-nums text-ink">{formatNumber(stats.avgReach)}</dd>
                </>
              ) : null}
              <dt className="text-ink-muted">Ներգրավվածություն</dt>
              <dd className="text-right tabular-nums text-ink">
                {stats.engagementRate !== undefined ? `${formatNumber(stats.engagementRate)}%` : "—"}
              </dd>
            </dl>
          )}
          {stats.bestWeekdays?.length ? (
            <p className="text-xs text-ink-muted">
              Լավագույն օրերը՝ {stats.bestWeekdays.map((d) => WEEKDAYS[d.group] ?? d.group).join(", ")}
              {stats.bestHours?.length ? `, ժամը՝ ${stats.bestHours.map((h) => h.group).join(", ")}` : ""}
            </p>
          ) : null}
          {stats.byKind?.length ? (
            <p className="text-xs text-ink-muted">
              {stats.byKind.map((k) => `${POST_KINDS[k.kind] ?? k.kind}՝ ${k.posts}`).join(" · ")}
            </p>
          ) : null}
        </Card>

        {account.network === "instagram" ? (
          <>
            <Card className="space-y-3">
              <p className="text-sm font-semibold text-ink">Ովքեր են հետևորդները</p>
              {audience?.age || audience?.gender ? (
                <div className="space-y-4">
                  {audience.gender ? (
                    <HBars
                      bars={Object.entries(audience.gender).map(([key, value]) => ({ key, label: GENDERS[key] ?? key, value }))}
                      color={network.color}
                    />
                  ) : null}
                  {audience.age ? (
                    <HBars
                      bars={Object.entries(audience.age)
                        .sort(([a], [b]) => a.localeCompare(b))
                        .map(([key, value]) => ({ key, label: `${key} տարեկան`, value }))}
                      color="#8a6bb0"
                    />
                  ) : null}
                </div>
              ) : (
                <p className="text-sm text-ink-muted">Meta-ն ցույց է տալիս հետևորդների տարիքն ու սեռը 100 հետևորդից սկսած։</p>
              )}
            </Card>

            <Card className="space-y-3">
              <p className="text-sm font-semibold text-ink">Քաղաքներ</p>
              {audience?.city ? (
                <HBars
                  bars={Object.entries(audience.city)
                    .slice(0, 6)
                    .map(([key, value]) => ({ key, label: key.split(",")[0], value }))}
                  color="#527589"
                />
              ) : (
                <p className="text-sm text-ink-muted">Կերևա, երբ Meta-ն տա հետևորդների քաղաքները։</p>
              )}
            </Card>
          </>
        ) : null}
      </div>

      {account.topPosts?.length ? <TopPosts posts={account.topPosts} /> : null}
    </section>
  );
}

function TopPosts({ posts }: { posts: SocialPostDto[] }) {
  return (
    <Card className="space-y-3">
      <p className="text-sm font-semibold text-ink">Լավագույն գրառումները 90 օրում</p>
      <ul className="divide-y divide-border">
        {posts.map((post, i) => (
          <li key={post.permalink ?? i} className="flex flex-wrap items-baseline justify-between gap-2 py-2.5 text-sm">
            <div className="min-w-0 flex-1">
              <p className="truncate text-ink">{post.caption || "Առանց տեքստի"}</p>
              <p className="text-xs text-ink-faint">
                {post.postedAt?.slice(0, 10).split("-").reverse().join(".")} · {POST_KINDS[post.kind ?? ""] ?? post.kind}
              </p>
            </div>
            <p className="shrink-0 text-xs tabular-nums text-ink-muted">
              {[
                post.likes !== undefined ? `${formatNumber(post.likes)} հավանում` : null,
                post.comments !== undefined ? `${formatNumber(post.comments)} մեկնաբանություն` : null,
                post.reach !== undefined ? `${formatNumber(post.reach)} ընդգրկում` : null,
              ]
                .filter(Boolean)
                .join(" · ")}
            </p>
            {post.permalink ? (
              <a href={post.permalink} target="_blank" rel="noreferrer" className="shrink-0 text-xs text-primary hover:underline">
                Բացել
              </a>
            ) : null}
          </li>
        ))}
      </ul>
    </Card>
  );
}
