import Link from "next/link";
import { requirePermission } from "@/lib/adminAuth";
import { getFeatureFlagsAdmin, getSiteSettingsAdmin } from "@/lib/adminApi";
import { AddressForm } from "./AddressForm";
import { BusinessProfileForm } from "./BusinessProfileForm";
import { ContactChannelsManager } from "./ContactChannelsManager";
import { PasswordForm } from "./PasswordForm";
import { PageHeader, SectionCard } from "@/ui";

export const dynamic = "force-dynamic";

export default async function AdminSettingsPage() {
  const admin = await requirePermission("settings");
  const [settings, flags] = await Promise.all([getSiteSettingsAdmin(), getFeatureFlagsAdmin()]);
  const business = settings.business ?? { name: admin.currentTenant?.name ?? "", logo: null };
  // With the site module, the logo and contacts live on the site's page.
  const site = flags.find((f) => f.key === "site")?.active ?? false;

  return (
    <div className="space-y-10">
      <PageHeader title="Կարգավորումներ" />

      {site ? (
        <SectionCard title="Լոգո և կոնտակտներ">
          <p className="text-sm text-ink-muted">
            Լոգոն, հասցեն և կապի եղանակները փոխվում են{" "}
            <Link href="/admin/site" className="font-medium text-primary underline-offset-2 hover:underline">
              «Կայք»
            </Link>{" "}
            բաժնում։
          </p>
        </SectionCard>
      ) : (
        <>
          <SectionCard
            title="Բիզնեսի պրոֆիլ"
            description="Անվանումը և լոգոն երևում են ադմին վահանակում և Phay-ի թիմին։"
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
        </>
      )}

      <SectionCard
        title="Գաղտնաբառ"
        description={`Ձեր մուտքի (${admin.email}) գաղտնաբառը։ Փոխելուց հետո մյուս սարքերում պետք կլինի նորից մուտք գործել։`}
      >
        <PasswordForm />
      </SectionCard>
    </div>
  );
}
