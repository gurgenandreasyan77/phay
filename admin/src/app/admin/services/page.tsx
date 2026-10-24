import Link from "next/link";
import { notFound } from "next/navigation";
import { requirePermission } from "@/lib/adminAuth";
import {
  getAdditionalServicesAdmin,
  getCalculatorSettingsAdmin,
  getFeatureFlagsAdmin,
  getServiceCategoriesAdmin,
  getServicesAdmin,
} from "@/lib/adminApi";
import { AddCalculatorButton } from "./AddCalculatorButton";
import { AdditionalServicesManager } from "./AdditionalServicesManager";
import { CalculatorSettingsForm } from "./CalculatorSettingsForm";
import { ServiceCategoriesManager } from "./ServiceCategoriesManager";
import { ServicesCatalog } from "./ServicesCatalog";
import { PageHeader, SectionCard, buttonClasses } from "@/ui";

export const dynamic = "force-dynamic";

/**
 * The services module: what the business does, for how much. Every kind of
 * work is a category here (car dry cleaning, upholstery...), not a module of
 * its own; a business that prices by a calculator adds one below.
 */
export default async function AdminServicesPage() {
  await requirePermission("catalog");
  const flags = await getFeatureFlagsAdmin();
  if (!flags.find((f) => f.key === "services")?.active) notFound();

  const [services, categories, calculator] = await Promise.all([
    getServicesAdmin(),
    getServiceCategoriesAdmin(),
    getCalculatorSettingsAdmin(),
  ]);
  const addOns = calculator ? await getAdditionalServicesAdmin() : [];

  return (
    <div className="space-y-10">
      <PageHeader
        title="Ծառայություններ"
        description="Ձեր բիզնեսի ծառայություններն ու գները։ Աշխատանքի ամեն տեսակ (օր.՝ ավտոմեքենայի քիմմաքրում, կահույքի քիմմաքրում) առանձին կատեգորիա է։ «Ակտիվ» ծառայությունները երևում են կայքում, «Սևագիր»-ը՝ միայն այստեղ։"
        actions={
          <Link href="/admin/services/details" className={buttonClasses("secondary")}>
            Մանրամասն էջեր
          </Link>
        }
      />

      <SectionCard title="Ծառայություններ">
        <ServicesCatalog services={services} categories={categories} />
      </SectionCard>

      <SectionCard
        title="Կատեգորիաներ"
        description="Ծառայությունների խմբերը, որոնցով դրանք դասավորվում են կայքում։ Օրինակ՝ «Ավտոքիմմաքրում» կատեգորիա ստեղծեք և դրա մեջ ավելացրեք ծառայությունները։"
      >
        <ServiceCategoriesManager items={categories} />
      </SectionCard>

      {calculator ? (
        <>
          <SectionCard
            title="Հաշվիչի սակագներ"
            description="Կիրառվում են կայքի գնահատման հաշվիչում և մաքրման տեսակների էջում ցուցադրվող «-ից» գներում։"
          >
            <CalculatorSettingsForm settings={calculator} />
          </SectionCard>

          <SectionCard
            title="Լրացուցիչ ծառայություններ"
            description="Հաշվիչին և գլխավոր էջի սլայդերին կից ծառայություններն ու գները։ «Ակտիվ»-ի հանումը թաքցնում է ծառայությունը կայքից՝ առանց ջնջելու։"
          >
            <AdditionalServicesManager items={addOns} />
          </SectionCard>
        </>
      ) : (
        <SectionCard
          title="Գնի հաշվիչ"
          description="Կայքում այցելուն ընտրում է տարածքը, մաքրման տեսակը և կահույքը ու տեսնում մոտավոր գինը։ Պետք է, եթե գինը կախված է մակերեսից կամ քանակից։"
        >
          <AddCalculatorButton />
        </SectionCard>
      )}
    </div>
  );
}
