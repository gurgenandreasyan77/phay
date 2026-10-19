import { notFound } from "next/navigation";
import { requirePermission } from "@/lib/adminAuth";
import { isPlatformStaff } from "@/lib/roles";
import { BusinessSupportChat } from "./BusinessSupportChat";
import { PageHeader } from "../_components/ui";

export const dynamic = "force-dynamic";

export default async function AdminSupportPage() {
  const admin = await requirePermission("settings");
  // Platform staff answer businesses from the platform level.
  if (isPlatformStaff(admin)) notFound();

  return (
    <div className="space-y-6">
      <PageHeader
        title="Կապ Servio-ի հետ"
        description="Հարց կամ խնդիր ունե՞ք։ Գրեք Servio-ի թիմին, և մենք կպատասխանենք այստեղ։"
      />
      <BusinessSupportChat />
    </div>
  );
}
