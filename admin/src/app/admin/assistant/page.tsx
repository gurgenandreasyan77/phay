import { requirePermission } from "@/lib/adminAuth";
import { getAssistantStatus } from "@/lib/adminApi";
import { PageHeader } from "../_components/ui";
import { AssistantChat } from "./AssistantChat";

export const dynamic = "force-dynamic";

export default async function AdminAssistantPage() {
  await requirePermission("insights");
  const status = await getAssistantStatus();

  return (
    <div className="space-y-6">
      <PageHeader
        title="AI օգնական"
        description="Հարցրեք ձեր վաճառքների մասին։ Օգնականը տեսնում է միայն ձեր բիզնեսի պատվերները, ծառայությունները և վերլուծությունը (առանց հաճախորդների անունների ու հեռախոսների) և խորհուրդ է տալիս, թե ինչպես ավելացնել վաճառքները։"
      />
      <AssistantChat
        configured={status?.configured ?? false}
        initialUsed={status?.used ?? 0}
        limit={status?.limit ?? 0}
      />
    </div>
  );
}
