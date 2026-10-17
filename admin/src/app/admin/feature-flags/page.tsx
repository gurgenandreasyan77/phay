import { notFound } from "next/navigation";
import { requireAdmin } from "@/lib/adminAuth";
import { getFeatureFlagsAdmin } from "@/lib/adminApi";
import { FeatureFlagsManager } from "./FeatureFlagsManager";
import { PageHeader, SectionCard } from "../_components/ui";

export const dynamic = "force-dynamic";

export default async function AdminFeatureFlagsPage() {
  const admin = await requireAdmin();
  // Platform screen: a business owner can't switch modules (the API says 403 too).
  if (!admin.isSuperAdmin) notFound();
  const flags = await getFeatureFlagsAdmin();

  return (
    <div className="space-y-10">
      <PageHeader
        title="Feature Flags"
        description="Գլոբալ անջատիչներ, որոնք ակտիվացնում կամ ամբողջովին անջատում են հավելվածի առանձին բաժիններ՝ բոլոր օգտատերերի համար։ Փոփոխությունը անմիջապես ազդում է կայքի, admin-ի և API-ի վրա։"
      />

      <SectionCard
        title="Գրանցված flag-եր"
        description="Նոր flag-եր ավելացվում են միայն backend-ում (App\\Features\\FeatureFlag) և ավտոմատ հայտնվում են այստեղ։"
      >
        <FeatureFlagsManager flags={flags} canManage={admin.isSuperAdmin} />
      </SectionCard>
    </div>
  );
}
