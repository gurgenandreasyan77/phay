import { notFound } from "next/navigation";
import { requirePermission } from "@/lib/adminAuth";
import { getAdditionalServicesAdmin, getCalculatorSettingsAdmin, getFeatureFlagsAdmin } from "@/lib/adminApi";
import { AdditionalServicesManager } from "./AdditionalServicesManager";
import { CalculatorSettingsForm } from "./CalculatorSettingsForm";
import { PageHeader, SectionCard } from "../_components/ui";

export const dynamic = "force-dynamic";

/** Servio's upholstery dry cleaning module: the calculator and its add-on services. */
export default async function AdminDryCleaningPage() {
  await requirePermission("catalog");
  // Without the module the business has no calculator (and opening this page
  // shouldn't create one with Servio's sofa prices).
  const flags = await getFeatureFlagsAdmin();
  if (!flags.find((f) => f.key === "dry-cleaning")?.active) notFound();

  const [items, calculatorSettings] = await Promise.all([
    getAdditionalServicesAdmin(),
    getCalculatorSettingsAdmin(),
  ]);

  return (
    <div className="space-y-10">
      <PageHeader
        title="Քիմմաքրում"
        description="Փափուկ կահույքի քիմմաքրման մոդուլ՝ գնահատման հաշվիչի սակագներ և լրացուցիչ ծառայություններ։"
      />

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
