import Link from "next/link";
import { Sparkles } from "lucide-react";
import { requireModule } from "@/lib/adminAuth";
import { getSocialState } from "@/lib/adminApi";
import { can } from "@/lib/roles";
import { buttonClasses, EmptyCard, PageHeader } from "@/ui";
import { AccountInsights } from "./AccountInsights";
import { Competitors } from "./Competitors";
import { SocialConnection } from "./SocialConnection";

export const dynamic = "force-dynamic";

/**
 * The social module: the business's Instagram and Facebook through Meta
 * (read only), its figures and posts, and competitors' public figures. The
 * AI assistant reads the same data. Only for a business the super admin
 * turned the module on for.
 */
export default async function AdminSocialPage(props: PageProps<"/admin/social">) {
  const me = await requireModule("social", "insights");

  const { meta } = await props.searchParams;
  const state = await getSocialState();
  const canManage = can(me, "settings");

  return (
    <div className="space-y-8">
      <PageHeader
        title="Սոցցանցեր"
        description="Ձեր Instagram-ը և Facebook էջը՝ հետևորդներ, ընդգրկում, լավագույն գրառումներ, և մրցակիցների հրապարակային թվերը։ AI օգնականը տեսնում է նույն տվյալները և խորհուրդ է տալիս, թե ինչ հրապարակել և երբ։"
        actions={
          <Link href="/admin/assistant" className={buttonClasses("secondary", "sm")}>
            <Sparkles className="h-4 w-4" />
            Հարցնել AI օգնականին
          </Link>
        }
      />

      {state === null ? (
        <EmptyCard>Չհաջողվեց բեռնել։ Թարմացրեք էջը։</EmptyCard>
      ) : (
        <>
          <SocialConnection state={state} canManage={canManage} notice={typeof meta === "string" ? meta : null} />

          {state.insights.map((account) => (
            <AccountInsights key={account.id} account={account} />
          ))}

          {state.configured ? (
            <Competitors
              competitors={state.competitors}
              comparison={state.comparison}
              max={state.maxCompetitors}
              canManage={canManage}
              hasInstagram={state.insights.some((a) => a.network === "instagram")}
            />
          ) : null}
        </>
      )}
    </div>
  );
}
