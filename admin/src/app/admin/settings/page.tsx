import { requirePermission } from "@/lib/adminAuth";
import { getSiteSettingsAdmin } from "@/lib/adminApi";
import { AddressForm } from "./AddressForm";
import { ContactChannelsManager } from "./ContactChannelsManager";
import { PageHeader, SectionCard } from "../_components/ui";

export const dynamic = "force-dynamic";

export default async function AdminSettingsPage() {
  await requirePermission("settings");
  const settings = await getSiteSettingsAdmin();

  return (
    <div className="space-y-10">
      <PageHeader title="Կարգավորումներ" />

      <SectionCard title="Հասցե" description="Ցուցադրվում է կայքի ֆուտերում և կոնտակտների էջում։">
        <AddressForm address={settings.address} />
      </SectionCard>

      <SectionCard
        title="Կապի եղանակներ"
        description="Հեռախոսահամարներ, messenger-ներ և սոց․ ցանցեր, որոնք ցուցադրվում են կայքում։"
      >
        <ContactChannelsManager channels={settings.channels} />
      </SectionCard>
    </div>
  );
}
