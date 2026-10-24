import { requireSuperAdmin } from "@/lib/adminAuth";
import { getPlatformUsersAdmin } from "@/lib/adminApi";
import { PlatformTeamManager } from "./PlatformTeamManager";
import { PageHeader } from "@/ui";

export const dynamic = "force-dynamic";

export default async function PlatformTeamPage() {
  const admin = await requireSuperAdmin();
  const users = await getPlatformUsersAdmin();

  return (
    <div className="space-y-8">
      <PageHeader
        title="Պլատֆորմի թիմ"
        description="Phay-ի աշխատակիցները պլատֆորմում։ Մոդերատորը ստեղծում է բիզնեսներ, կայքեր և մուտքեր և կարող է մտնել ցանկացած բիզնես, բայց չի փոխում մոդուլները և թիմը։"
      />
      <PlatformTeamManager users={users} meId={admin.id} />
    </div>
  );
}
