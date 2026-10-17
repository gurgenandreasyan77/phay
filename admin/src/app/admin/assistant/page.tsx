import { requirePermission } from "@/lib/adminAuth";
import { getAssistantStatus, getFeatureFlagsAdmin } from "@/lib/adminApi";
import { PageHeader } from "../_components/ui";
import { AssistantChat } from "./AssistantChat";
import { SUGGESTIONS } from "./suggestions";

export const dynamic = "force-dynamic";

export default async function AdminAssistantPage() {
  await requirePermission("insights");
  const [status, flags] = await Promise.all([getAssistantStatus(), getFeatureFlagsAdmin()]);
  const ordersOn = flags.find((f) => f.key === "orders")?.active ?? true;

  return (
    <div className="space-y-6">
      <PageHeader
        title="AI օգնական"
        description="Հարցրեք ձեր վաճառքների մասին։ Օգնականը տեսնում է միայն ձեր բիզնեսի տվյալները՝ պատվերները, ծառայությունները, ձեր աղյուսակները և վերլուծությունը (առանց հաճախորդների անունների ու հեռախոսների), և խորհուրդ է տալիս, թե ինչպես ավելացնել վաճառքները։"
      />
      <AssistantChat
        suggestions={ordersOn ? SUGGESTIONS.orders : SUGGESTIONS.sales}
        configured={status?.configured ?? false}
        initialUsed={status?.used ?? 0}
        limit={status?.limit ?? 0}
      />
    </div>
  );
}
