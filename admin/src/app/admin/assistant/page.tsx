import { requirePermission } from "@/lib/adminAuth";
import { can, isPlatformStaff } from "@/lib/roles";
import { getAssistantStatus, getDataTables } from "@/lib/adminApi";
import { PageHeader } from "../_components/ui";
import { AssistantChat } from "./AssistantChat";
import { SUGGESTIONS } from "./suggestions";

export const dynamic = "force-dynamic";

export default async function AdminAssistantPage() {
  const admin = await requirePermission("insights");
  const [status, tables] = await Promise.all([getAssistantStatus(), getDataTables()]);
  // A shop that keeps only sales gets questions about sales.
  const salesOnly = tables.some((t) => t.section === "sales") && !tables.some((t) => t.section === "orders");

  return (
    <div className="space-y-6">
      <PageHeader
        title="AI օգնական"
        description="Հարցրեք ձեր վաճառքների, պատվերների, ծախսերի և շահույթի մասին՝ ցանկացած օրվա, ամսվա կամ ապրանքի։ Օգնականը ինքն է գտնում ճշգրիտ թվերը ձեր բիզնեսի տվյալներում (առանց հաճախորդների անունների ու հեռախոսների) և խորհուրդ է տալիս, թե ինչպես ավելացնել վաճառքները։"
      />
      <AssistantChat
        suggestions={salesOnly ? SUGGESTIONS.sales : SUGGESTIONS.orders}
        configured={status?.configured ?? false}
        initialUsage={
          status ?? {
            used: 0,
            limit: 0,
            planLimit: 0,
            bonus: 0,
            remaining: 0,
            unlimited: false,
            periodStart: new Date().toISOString(),
            resetsAt: new Date().toISOString(),
          }
        }
        plans={status?.offer.plans ?? []}
        // Asking for a plan or more questions goes to the chat with the platform.
        canAsk={can(admin, "settings") && !isPlatformStaff(admin)}
      />
    </div>
  );
}
