import { notFound } from "next/navigation";
import { requireAdmin } from "@/lib/adminAuth";
import { getTenantsAdmin } from "@/lib/adminApi";
import { BusinessesManager } from "./BusinessesManager";
import { PageHeader } from "../_components/ui";

export const dynamic = "force-dynamic";

export default async function AdminBusinessesPage() {
  const admin = await requireAdmin();
  // Platform screen: creating businesses is super-admin only for now.
  if (!admin.isSuperAdmin) notFound();

  const tenants = await getTenantsAdmin();

  return (
    <div className="space-y-8">
      <PageHeader
        title="Բիզնեսներ"
        description="Պլատֆորմի բոլոր բիզնեսները։ Յուրաքանչյուր բիզնես ունի իր ծառայությունները, պատվերները, կարգավորումները և կայքերը։"
      />
      <BusinessesManager tenants={tenants} currentTenantId={admin.currentTenant.id} />
    </div>
  );
}
