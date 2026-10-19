import { requireAdmin } from "@/lib/adminAuth";
import { PasswordForm } from "../settings/PasswordForm";
import { PageHeader, SectionCard } from "../_components/ui";

export const dynamic = "force-dynamic";

/** The signed-in person's own account, for every role (settings are for owners and admins). */
export default async function AdminAccountPage() {
  const admin = await requireAdmin({ allowLocked: true });

  return (
    <div className="space-y-10">
      <PageHeader title="Իմ հաշիվը" description={`${admin.name} · ${admin.email}`} />

      <SectionCard title="Գաղտնաբառ" description="Փոխելուց հետո մյուս սարքերում պետք կլինի նորից մուտք գործել։">
        <PasswordForm />
      </SectionCard>
    </div>
  );
}
