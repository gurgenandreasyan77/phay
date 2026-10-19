import { requirePermission } from "@/lib/adminAuth";
import { getSiteSettingsAdmin } from "@/lib/adminApi";
import { AddressForm } from "./AddressForm";
import { BusinessProfileForm } from "./BusinessProfileForm";
import { ContactChannelsManager } from "./ContactChannelsManager";
import { PasswordForm } from "./PasswordForm";
import { PageHeader, SectionCard } from "../_components/ui";

export const dynamic = "force-dynamic";

export default async function AdminSettingsPage() {
  const admin = await requirePermission("settings");
  const settings = await getSiteSettingsAdmin();
  const business = settings.business ?? { name: admin.currentTenant?.name ?? "", logo: null };

  return (
    <div className="space-y-10">
      <PageHeader title="Կարգավորումներ" />

      <SectionCard
        title="Բիզնեսի պրոֆիլ"
        description="Անվանումը և լոգոն երևում են ադմին վահանակում և Servio-ի թիմին։"
      >
        <BusinessProfileForm key={`${business.name}|${business.logo}`} name={business.name} logo={business.logo} />
      </SectionCard>

      <SectionCard title="Հասցե" description="Ցուցադրվում է կայքի ֆուտերում և կոնտակտների էջում։">
        <AddressForm address={settings.address} />
      </SectionCard>

      <SectionCard
        title="Կապի եղանակներ"
        description="Հեռախոսահամարներ, messenger-ներ և սոց․ ցանցեր, որոնք ցուցադրվում են կայքում։"
      >
        <ContactChannelsManager channels={settings.channels} />
      </SectionCard>

      <SectionCard
        title="Գաղտնաբառ"
        description={`Ձեր մուտքի (${admin.email}) գաղտնաբառը։ Փոխելուց հետո մյուս սարքերում պետք կլինի նորից մուտք գործել։`}
      >
        <PasswordForm />
      </SectionCard>
    </div>
  );
}
