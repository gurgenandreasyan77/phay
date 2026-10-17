import { notFound } from "next/navigation";
import { requireAdmin } from "@/lib/adminAuth";
import { getSitesAdmin, getTenantsAdmin } from "@/lib/adminApi";
import { SitesManager } from "./SitesManager";
import { PageHeader } from "../../_components/ui";

export const dynamic = "force-dynamic";

export default async function AdminSitesPage({ searchParams }: { searchParams: Promise<{ tenant?: string }> }) {
  const admin = await requireAdmin();
  // Platform screen: a business owner doesn't manage site keys (the API says 403 too).
  if (!admin.isSuperAdmin) notFound();

  const [sites, tenants, { tenant }] = await Promise.all([getSitesAdmin(), getTenantsAdmin(), searchParams]);

  return (
    <div className="space-y-8">
      <PageHeader
        title="Կայքեր"
        description="Պլատֆորմի բոլոր բիզնեսների կայքերը։ Կայքն իր բանալիով ստանում է միայն իր բիզնեսի ծառայություններն ու կարգավորումները, իսկ նրա հայտերը գալիս են այդ բիզնեսի «Պատվերներ» բաժին։"
      />
      <SitesManager sites={sites} tenants={tenants} initialTenantId={tenant ? Number(tenant) || null : null} />
    </div>
  );
}
