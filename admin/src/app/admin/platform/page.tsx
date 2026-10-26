import { requirePlatformStaff } from "@/lib/adminAuth";
import { getTenantsAdmin } from "@/lib/adminApi";
import { isSuperAdmin } from "@/lib/roles";
import { BusinessesManager } from "./BusinessesManager";
import { PageHeader } from "@/ui";

export const dynamic = "force-dynamic";

export default async function AdminBusinessesPage({ searchParams }: { searchParams: Promise<{ q?: string }> }) {
  const admin = await requirePlatformStaff();
  const search = ((await searchParams).q ?? "").trim();

  const tenants = await getTenantsAdmin(search);

  return (
    <div className="space-y-8">
      <PageHeader
        title="Բիզնեսներ"
        description="Պլատֆորմի բոլոր բիզնեսները։ «Մտնել»-ը բացում է բիզնեսի ակտիվությունը, բաժանորդագրությունը և մոդուլները․ նրա պատվերներն ու եկամուտները փակ են։"
      />
      <BusinessesManager
        tenants={tenants}
        search={search}
        isSuperAdmin={isSuperAdmin(admin)}
      />
    </div>
  );
}
