import { redirect } from "next/navigation";
import { requirePermission } from "@/lib/adminAuth";
import { isPlatformStaff } from "@/lib/roles";
import { BusinessSupportChat } from "./BusinessSupportChat";
import { PageHeader } from "@/ui";

export const dynamic = "force-dynamic";

export default async function AdminSupportPage() {
  const admin = await requirePermission("settings", { allowLocked: true });
  // Platform staff answer businesses from the platform level.
  if (isPlatformStaff(admin)) redirect("/admin/platform/support");

  return (
    <div className="space-y-6">
      <PageHeader
        title="Կապ Phay-ի հետ"
        description="Հարց կամ խնդիր ունե՞ք։ Գրեք Phay-ի թիմին, և մենք կպատասխանենք այստեղ։"
      />
      <BusinessSupportChat />
    </div>
  );
}
