import { requireAdmin } from "@/lib/adminAuth";
import { getAccountSecurity } from "@/lib/adminApi";
import { PasswordForm } from "../settings/PasswordForm";
import { PageHeader, SectionCard } from "@/ui";
import { SecurityPanel } from "./SecurityPanel";

export const dynamic = "force-dynamic";

/** The signed-in person's own account and its security, for every role (settings are for owners and admins). */
export default async function AdminAccountPage() {
  const admin = await requireAdmin({ allowLocked: true });
  const security = await getAccountSecurity();

  return (
    <div className="space-y-10">
      <PageHeader title="Իմ հաշիվը" description={`${admin.name} · ${admin.email}`} />

      <SectionCard title="Գաղտնաբառ" description="Փոխելուց հետո մյուս սարքերում պետք կլինի նորից մուտք գործել։">
        <PasswordForm />
      </SectionCard>

      {security ? <SecurityPanel security={security} /> : null}
    </div>
  );
}
