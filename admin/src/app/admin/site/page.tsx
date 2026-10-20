import Link from "next/link";
import { notFound } from "next/navigation";
import { requirePermission } from "@/lib/adminAuth";
import { can } from "@/lib/roles";
import {
  getFeatureFlagsAdmin,
  getFeedbackMessages,
  getPostsAdmin,
  getServiceGalleryItemsAdmin,
  getServiceVideosAdmin,
  getServicesAdmin,
  getSiteSettingsAdmin,
} from "@/lib/adminApi";
import { AddressForm } from "../settings/AddressForm";
import { BusinessProfileForm } from "../settings/BusinessProfileForm";
import { ContactChannelsManager } from "../settings/ContactChannelsManager";
import { GalleryManager } from "./GalleryManager";
import { VideosManager } from "./VideosManager";
import { Card, PageHeader, SectionCard, buttonClasses } from "../_components/ui";

export const dynamic = "force-dynamic";

/**
 * Site management: what the business shows on the site the platform gave
 * it. Logo and contacts, videos (before/after and informational), photos of
 * the results, the blog and what visitors wrote through the feedback form.
 */
export default async function AdminSitePage() {
  const me = await requirePermission("catalog");
  const flags = await getFeatureFlagsAdmin();
  if (!flags.find((f) => f.key === "site")?.active) notFound();

  const profile = can(me, "settings");
  const [settings, videos, gallery, services, posts, feedback] = await Promise.all([
    profile ? getSiteSettingsAdmin() : null,
    getServiceVideosAdmin(),
    getServiceGalleryItemsAdmin(),
    getServicesAdmin(),
    getPostsAdmin(),
    can(me, "orders") ? getFeedbackMessages() : null,
  ]);
  const business = settings?.business ?? { name: me.currentTenant?.name ?? "", logo: null };
  const published = (posts ?? []).filter((p) => p.status === "published").length;

  return (
    <div className="space-y-10">
      <PageHeader
        title="Կայք"
        description="Այն ամենը, ինչ ձեր կայքը ցույց է տալիս այցելուներին՝ լոգո և կոնտակտներ, տեսանյութեր, աշխատանքների «մինչև/հետո» լուսանկարներ, բլոգ և հետադարձ կապի ձևից եկած հաղորդագրություններ։"
      />

      <div className="grid gap-4 sm:grid-cols-2">
        <Card className="flex flex-col justify-between gap-3">
          <div>
            <p className="text-sm font-semibold text-ink">Բլոգ</p>
            <p className="text-sm text-ink-muted">
              {posts === null ? "Չհաջողվեց բեռնել։" : `${posts.length} հոդված, որից ${published}-ը՝ հրապարակված։`}
            </p>
          </div>
          <Link href="/admin/site/blog" className={buttonClasses("secondary", "sm", "self-start")}>
            Բացել բլոգը
          </Link>
        </Card>
        {feedback ? (
          <Card className="flex flex-col justify-between gap-3">
            <div>
              <p className="text-sm font-semibold text-ink">Հետադարձ կապ</p>
              <p className="text-sm text-ink-muted">
                {feedback.meta.total === 0
                  ? "Կայքի ձևից դեռ հաղորդագրություններ չկան։"
                  : `${feedback.meta.total} հաղորդագրություն, չկարդացած՝ ${feedback.meta.unread}։`}
              </p>
            </div>
            <Link href="/admin/site/feedback" className={buttonClasses("secondary", "sm", "self-start")}>
              Բացել հաղորդագրությունները
            </Link>
          </Card>
        ) : null}
      </div>

      {settings ? (
        <>
          <SectionCard title="Լոգո և անվանում" description="Երևում են կայքում, ադմին վահանակում և Servio-ի թիմին։">
            <BusinessProfileForm key={`${business.name}|${business.logo}`} name={business.name} logo={business.logo} />
          </SectionCard>

          <SectionCard title="Հասցե" description="Ցուցադրվում է կայքի ֆուտերում և կոնտակտների էջում։">
            <AddressForm address={settings.address} />
          </SectionCard>

          <SectionCard
            title="Կապի եղանակներ"
            description="Հեռախոսահամարներ, messenger-ներ և սոց․ ցանցեր, որոնք ցուցադրվում են կայքում։"
          >
            <ContactChannelsManager channels={settings.channels} />
          </SectionCard>
        </>
      ) : null}

      <SectionCard
        title="Տեսանյութեր"
        description="«Մինչև/հետո» տեսանյութերը ցույց են տալիս աշխատանքի արդյունքը, տեղեկատվականները՝ ինչպես եք աշխատում, խորհուրդներ, նորություններ։ Կայքում երևում են միայն «Հրապարակված»-ները։"
      >
        <VideosManager items={videos} services={services} />
      </SectionCard>

      <SectionCard
        title="«Մինչև/հետո» լուսանկարներ"
        description="Հրապարակվում են կայքում միայն այն դեպքում, երբ և՛ հաճախորդի համաձայնությունը, և՛ հրապարակման նշիչը միացված են։"
      >
        <GalleryManager items={gallery} services={services} />
      </SectionCard>
    </div>
  );
}
