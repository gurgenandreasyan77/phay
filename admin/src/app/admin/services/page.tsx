import { requireAdmin } from "@/lib/adminAuth";
import { getAdditionalServicesAdmin, getCalculatorSettingsAdmin } from "@/lib/adminApi";
import { AdditionalServicesManager } from "./AdditionalServicesManager";
import { CalculatorSettingsForm } from "./CalculatorSettingsForm";
import { PageHeader, SectionCard } from "../_components/ui";

export const dynamic = "force-dynamic";

export default async function AdminServicesPage() {
  await requireAdmin();
  const [items, calculatorSettings] = await Promise.all([
    getAdditionalServicesAdmin(),
    getCalculatorSettingsAdmin(),
  ]);

  return (
    <div className="space-y-10">
      <PageHeader title="Ծառայություններ և գներ" />

      <SectionCard
        title="Լրացուցիչ ծառայություններ"
        description="Գլխավոր էջի սլայդերում ցուցադրվող ծառայություններն ու գները։ «Ակտիվ»-ի հանումը թաքցնում է ծառայությունը կայքից՝ առանց ջնջելու. հետ միացնելով այն նորից հայտնվում է կայքում։"
      >
        <AdditionalServicesManager items={items} />
      </SectionCard>

      <SectionCard
        title="Հաշվիչի սակագներ"
        description="Կիրառվում են գնահատման հաշվիչում և ստանդարտ մաքրման 3 տեսակների էջում ցուցադրվող «-ից» գներում։"
      >
        {calculatorSettings ? (
          <CalculatorSettingsForm settings={calculatorSettings} />
        ) : (
          <p className="text-sm text-danger">Չհաջողվեց բեռնել հաշվիչի կարգավորումները։</p>
        )}
      </SectionCard>
    </div>
  );
}
