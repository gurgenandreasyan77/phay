import { requireAdmin } from "@/lib/adminAuth";
import { getServices } from "@/lib/api";
import { NewOrderForm } from "./NewOrderForm";
import { requireSectionModule } from "../../_components/tables/views";
import { Card, PageHeader } from "../../_components/ui";

export default async function NewOrderPage() {
  await requireAdmin();
  await requireSectionModule("orders");
  const services = await getServices("hy");

  return (
    <div className="mx-auto max-w-2xl space-y-6">
      <PageHeader title="Ավելացնել պատվեր" />
      <Card>
        <NewOrderForm services={services} />
      </Card>
    </div>
  );
}
