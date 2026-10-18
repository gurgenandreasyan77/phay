import { requirePermission } from "@/lib/adminAuth";
import { getEmployees } from "@/lib/adminApi";
import { PageHeader, SectionCard } from "../_components/ui";
import { EmployeesList, NewEmployeeForm } from "./EmployeesManager";

export const dynamic = "force-dynamic";

/**
 * Employees: the people the owner or the admin adds here, and the orders
 * each one did. A row is someone's when its «Կատարող» column holds their
 * name; the figures by day, week, month… are on the orders page.
 */
export default async function AdminEmployeesPage() {
  await requirePermission("settings");
  const employees = await getEmployees();

  return (
    <div className="space-y-8">
      <PageHeader
        title="Աշխատակիցներ"
        description="Ավելացրեք ձեր աշխատակիցներին, և պատվերների աղյուսակի «Կատարող» դաշտում ընտրեք, թե ով է կատարել պատվերը։ Աշխատակցի անունին սեղմելով կտեսնեք նրա պատվերները։"
      />

      <NewEmployeeForm />

      <SectionCard title={`Ցանկ (${employees.length})`}>
        <EmployeesList employees={employees} />
      </SectionCard>
    </div>
  );
}
