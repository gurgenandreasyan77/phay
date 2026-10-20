import Link from "next/link";
import type { AudienceDto, AudienceGroupDto } from "@/lib/adminApi";
import { formatNumber, tablesPath } from "../tables/shared";
import { Card, SectionCard } from "../ui";
import { BarChart } from "./BarChart";
import { DonutChart, RingStat, SampleFrame, type Slice } from "./Charts";
import { money } from "./format";

const GENDER_LABELS: Record<string, string> = { female: "Իգական", male: "Արական", other: "Այլ" };
const AGE_LABELS: Record<string, string> = { "<18": "մինչև 18", "55+": "55 և ավելի" };

const SAMPLE_CHANNELS: Slice[] = [
  { key: "instagram", label: "Instagram", value: 46 },
  { key: "facebook", label: "Facebook", value: 21 },
  { key: "կայք", label: "Կայք", value: 18 },
  { key: "telegram", label: "Telegram", value: 9 },
];
const SAMPLE_GENDER: Slice[] = [
  { key: "female", label: "Իգական", value: 62 },
  { key: "male", label: "Արական", value: 38 },
];
const SAMPLE_AGE = [4, 18, 41, 23, 9, 5];

/** "Իգական"… with the amount under, for the legend. */
function genderSlices(groups: AudienceGroupDto[]): Slice[] {
  return groups.map((g) => ({
    key: g.key,
    label: GENDER_LABELS[g.key] ?? g.key,
    value: g.rows,
    note: g.amount ? money(g.amount) : undefined,
  }));
}

function ageBars(groups: { key: string; rows: number; amount: number | null }[]) {
  return groups.map((g) => ({
    key: g.key,
    label: AGE_LABELS[g.key] ?? g.key,
    title: `${AGE_LABELS[g.key] ?? g.key} տարեկան`,
    value: g.rows,
    details: g.amount ? [`Եկամուտ՝ ${money(g.amount)}`] : [],
  }));
}

function Hint({ children }: { children: React.ReactNode }) {
  return <p className="text-xs leading-relaxed text-ink-muted">{children}</p>;
}

/**
 * Who buys and where they come from: channels, social accounts, gender and
 * age. A block with no data yet shows a dimmed sample and what to add.
 */
export function AudienceSection({ audience }: { audience: AudienceDto | null }) {
  const channels = audience?.channels ?? null;
  const gender = audience?.gender ?? null;
  const age = audience?.age ?? null;
  const social = audience?.social ?? [];
  const genderTotal = gender?.reduce((sum, g) => sum + g.rows, 0) ?? 0;
  const leader = gender && genderTotal > 0 ? [...gender].sort((a, b) => b.rows - a.rows)[0] : null;
  const ageTop = age && age.some((g) => g.rows > 0) ? [...age].sort((a, b) => b.rows - a.rows)[0] : null;
  const ageTotal = age?.reduce((sum, g) => sum + g.rows, 0) ?? 0;

  return (
    <section className="space-y-4 border-t border-border pt-8">
      <div>
        <h2 className="text-lg font-semibold text-ink">Հաճախորդներ և աղբյուրներ</h2>
        <p className="mt-1 max-w-2xl text-sm text-ink-muted">
          Որտեղից են գալիս ձեր հաճախորդները, ովքեր են ավելի շատ գնում՝ ըստ սեռի և տարիքի։ Միայն ընդհանուր տոկոսներ, առանց անձնական տվյալների։
        </p>
      </div>

      <div className="grid gap-6 lg:grid-cols-2">
        <SectionCard title="Որտեղից են գալիս">
          <Card>
            {channels && channels.length > 0 ? (
              <DonutChart
                slices={channels.map((c) => ({ key: c.key, label: c.label, value: c.rows, note: c.amount ? money(c.amount) : undefined }))}
                total="գնում"
                caption="Գնումներն ըստ աղբյուրի"
              />
            ) : (
              <div className="space-y-3">
                <SampleFrame>
                  <DonutChart slices={SAMPLE_CHANNELS} total="գնում" />
                </SampleFrame>
                <Hint>
                  Ավելացրեք «Աղբյուր» սյունակ ձեր{" "}
                  <Link href={tablesPath("orders")} className="text-primary hover:underline">
                    աղյուսակներում
                  </Link>{" "}
                  (Instagram, Facebook, կայք…), և այստեղ կտեսնեք, թե որ ցանցն է ավելի շատ հաճախորդ բերում։
                </Hint>
              </div>
            )}
          </Card>
        </SectionCard>

        <SectionCard title="Սոցիալական ցանցեր">
          <Card>
            {social.length > 0 ? (
              <div className="grid gap-4 sm:grid-cols-2">
                {social.map((account) => (
                  <SocialTile key={`${account.network}-${account.handle}`} {...account} />
                ))}
              </div>
            ) : (
              <div className="space-y-3">
                <SampleFrame badge="Շուտով">
                  <div className="grid gap-4 sm:grid-cols-2">
                    <SocialTile network="instagram" handle="@servio.am" followers={4820} followersChange={312} reach={18400} engagement={5.8} />
                    <SocialTile network="facebook" handle="Servio" followers={2140} followersChange={64} reach={7300} engagement={3.1} />
                  </div>
                </SampleFrame>
                <Hint>
                  Instagram-ի և Facebook-ի միացումը շուտով։ Այստեղ կերևան հետևորդները, դիտումները և ներգրավվածությունը՝ ձեր
                  պատվերների կողքին։
                </Hint>
              </div>
            )}
          </Card>
        </SectionCard>

        <SectionCard title="Ըստ սեռի">
          <Card className="space-y-3">
            {gender ? (
              <>
                <DonutChart slices={genderSlices(gender)} total="գնում" caption="Գնումներն ըստ սեռի" />
                {leader && leader.key !== "other" ? (
                  <p className="text-sm text-ink">
                    Ավելի շատ գնում են {leader.key === "female" ? "կանայք" : "տղամարդիկ"}՝{" "}
                    <span className="font-semibold">{Math.round((leader.rows / genderTotal) * 100)}%</span>։
                  </p>
                ) : null}
              </>
            ) : (
              <>
                <SampleFrame>
                  <DonutChart slices={SAMPLE_GENDER} total="գնում" />
                </SampleFrame>
                <Hint>Ավելացրեք «Սեռ» սյունակ ձեր աղյուսակում, և կտեսնեք, թե ով է ավելի շատ գնում՝ կանայք, թե տղամարդիկ։</Hint>
              </>
            )}
          </Card>
        </SectionCard>

        <SectionCard title="Ըստ տարիքի">
          <Card className="space-y-3">
            {age ? (
              <>
                <BarChart bars={ageBars(age)} format={(n) => `${formatNumber(n)} գնում`} color="#8a6bb0" />
                {ageTop && ageTotal > 0 ? (
                  <RingStat
                    value={ageTop.rows}
                    of={ageTotal}
                    size={72}
                    color="#8a6bb0"
                    label={`Ամենաակտիվ խումբը՝ ${AGE_LABELS[ageTop.key] ?? ageTop.key} տարեկան`}
                    hint="Գնումների մասնաբաժինը այս ժամանակահատվածում"
                  />
                ) : null}
              </>
            ) : (
              <>
                <SampleFrame>
                  <BarChart
                    bars={ageBars(["<18", "18-24", "25-34", "35-44", "45-54", "55+"].map((key, i) => ({ key, rows: SAMPLE_AGE[i], amount: null })))}
                    format={(n) => `${n}`}
                    color="#8a6bb0"
                  />
                </SampleFrame>
                <Hint>Ավելացրեք «Տարիք» սյունակ (34 կամ 25-34), և կտեսնեք, թե որ տարիքի հաճախորդներն են ավելի շատ գնում։</Hint>
              </>
            )}
          </Card>
        </SectionCard>
      </div>
    </section>
  );
}

const NETWORKS: Record<string, { label: string; color: string }> = {
  instagram: { label: "Instagram", color: "#d1477a" },
  facebook: { label: "Facebook", color: "#3b69c9" },
  tiktok: { label: "TikTok", color: "#2b2f33" },
};

function SocialTile({
  network,
  handle,
  followers,
  followersChange,
  reach,
  engagement,
}: AudienceDto["social"][number]) {
  const info = NETWORKS[network] ?? { label: network, color: "var(--color-primary)" };
  return (
    <div className="space-y-3 rounded-lg border border-border p-4" style={{ borderTop: `3px solid ${info.color}` }}>
      <div>
        <p className="text-sm font-semibold" style={{ color: info.color }}>
          {info.label}
        </p>
        <p className="text-xs text-ink-muted">{handle}</p>
      </div>
      <div>
        <p className="text-2xl font-semibold text-ink">{formatNumber(followers)}</p>
        <p className="text-xs text-ink-muted">
          հետևորդ{" "}
          <span className={followersChange >= 0 ? "text-success" : "text-danger"}>
            {followersChange >= 0 ? "+" : ""}
            {formatNumber(followersChange)}
          </span>
        </p>
      </div>
      <RingStat
        value={engagement}
        of={10}
        size={60}
        color={info.color}
        display={`${formatNumber(engagement)}%`}
        label="Ներգրավվածություն"
        hint={`${formatNumber(reach)} դիտում`}
      />
    </div>
  );
}
