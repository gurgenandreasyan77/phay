import { requirePlatformStaff } from "@/lib/adminAuth";
import { isSuperAdmin } from "@/lib/roles";
import { getSupportConversations, getTenantsAdmin } from "@/lib/adminApi";
import { PlatformSupport } from "./PlatformSupport";
import { PageHeader } from "@/ui";

export const dynamic = "force-dynamic";

export default async function PlatformSupportPage({ searchParams }: { searchParams: Promise<{ tenant?: string }> }) {
  const me = await requirePlatformStaff();
  const [{ tenant }, conversations, tenants] = await Promise.all([
    searchParams,
    getSupportConversations(),
    getTenantsAdmin(),
  ]);

  return (
    <div className="space-y-6">
      <PageHeader
        title="Հաղորդագրություններ"
        description="Զրույցներ բիզնեսների հետ։ Նոր հաղորդագրությունները նշված են թվով։ Անդորրագիր ստանալուց հետո համալրեք բիզնեսի հաշվեկշիռը հենց այստեղ։"
      />
      <PlatformSupport
        initialConversations={conversations}
        tenants={tenants.map((t) => ({ id: t.id, name: t.name, logo: t.logo ?? null }))}
        initialTenantId={tenant ? Number(tenant) : null}
        superAdmin={isSuperAdmin(me)}
      />
    </div>
  );
}
