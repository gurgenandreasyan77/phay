import Link from "next/link";
import { requireAdmin } from "@/lib/adminAuth";
import {
  getServiceCategoriesAdmin,
  getServicesAdmin,
  getServiceGalleryItemsAdmin,
  getServiceVideosAdmin,
  getFeatureFlagsAdmin,
} from "@/lib/adminApi";
import { ServicesManager } from "./ServicesManager";
import { GalleryManager } from "./GalleryManager";
import { VideosManager } from "./VideosManager";
import { PageHeader, SectionCard } from "../_components/ui";

export const dynamic = "force-dynamic";

export default async function AdminAutoCarePage() {
  await requireAdmin();

  const [categories, services, gallery, videos, flags] = await Promise.all([
    getServiceCategoriesAdmin(),
    getServicesAdmin(),
    getServiceGalleryItemsAdmin(),
    getServiceVideosAdmin(),
    getFeatureFlagsAdmin(),
  ]);

  const autoCareEnabled = flags.find((f) => f.key === "auto-care")?.active ?? true;

  const autoCareServices = services.filter((s) => {
    const category = categories.find((c) => c.id === s.categoryId);
    return category?.slug === "auto-care";
  });

  return (
    <div className="space-y-10">
      <PageHeader
        title="Ավտոխնամք"
        description="Ավտոխնամքի էջը (/services/auto-care) այժմ պարզ տեղեկատվական էջ է՝ գների ցանկով, տեսանյութերով, «մինչև/հետո» օրինակներով և Market-ի ապրանքներով։ Չկա հայտի ձև կամ առանձին ծառայության էջ։"
      />

      {!autoCareEnabled ? (
        <SectionCard
          title="Ավտոխնամքի մոդուլը անջատված է"
          description="Այս բիզնեսի «Ավտոխնամք» մոդուլն անջատված է, ուստի /services/auto-care էջը և դրա տվյալները ժամանակավորապես անհասանելի են կայքում։ Տվյալները պահված են և նորից հասանելի կդառնան մոդուլը միացնելուց հետո։"
        >
          <p className="text-sm text-ink-muted">
            Մոդուլը միացնում է պլատֆորմի ադմինը՝{" "}
            <Link href="/admin/platform" className="font-medium text-primary underline-offset-2 hover:underline">
              Բիզնեսներ → Մոդուլներ
            </Link>
            ։
          </p>
        </SectionCard>
      ) : (
        <>
          <SectionCard
            title="Ծառայություններ և գներ"
            description={
              <>
                Ցուցադրվում են որպես գների ցանկ ավտոխնամքի էջում. <strong>Սևագիր</strong> և{" "}
                <strong>Արխիվացված</strong> — չեն երևում կայքում, <strong>Ակտիվ</strong> — երևում է գնով,{" "}
                <strong>Շուտով</strong> և <strong>Ժամանակավոր անհասանելի</strong> — երևում են՝ համապատասխան
                նշիչով։
              </>
            }
          >
            <ServicesManager items={autoCareServices} categories={categories} />
          </SectionCard>

          <SectionCard
            title="Տեսանյութեր"
            description="Ինչպես ենք կատարում սրահի քիմմաքրումը. կարճ տեսանյութեր, որոնք երևում են ավտոխնամքի էջում։ Հրապարակվում է միայն «Հրապարակված» նշիչը միացնելուց հետո։"
          >
            <VideosManager items={videos} services={autoCareServices} />
          </SectionCard>

          <SectionCard
            title="«Մինչև/հետո» պատկերասրահ"
            description="Հրապարակվում է կայքում միայն այն դեպքում, երբ և՛ հաճախորդի համաձայնությունը, և՛ հրապարակման նշիչը միացված են։"
          >
            <GalleryManager items={gallery} services={autoCareServices} />
          </SectionCard>
        </>
      )}
    </div>
  );
}
