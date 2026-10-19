import { requireSuperAdmin } from "@/lib/adminAuth";
import { getModulePricesAdmin, getPlansAdmin } from "@/lib/adminApi";
import { PageHeader } from "../../_components/ui";
import { ModulePricesForm } from "./ModulePricesForm";
import { PlansManager } from "./PlansManager";

export const dynamic = "force-dynamic";

/** Subscription plans and module prices (super admin). */
export default async function PlansPage() {
  await requireSuperAdmin();
  const [plans, modules] = await Promise.all([getPlansAdmin(), getModulePricesAdmin()]);

  return (
    <div className="space-y-10">
      <PageHeader
        title="Փաթեթներ և գներ"
        description="Բիզնեսի ամսական վճարը՝ նրա փաթեթի գինը գումարած միացված մոդուլների գները (բացի փաթեթում ներառվածներից)։ Նոր բիզնեսը սկսում է հիմնական փաթեթի փորձաշրջանով։"
      />
      <PlansManager plans={plans} modules={modules} />
      <ModulePricesForm modules={modules} />
    </div>
  );
}
