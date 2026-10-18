import { requirePermission } from "@/lib/adminAuth";
import { getEmployeeGroups, getEmployees } from "@/lib/adminApi";
import { PageHeader, SectionCard } from "../_components/ui";
import { EmployeesList, GroupsManager, NewEmployeeForm } from "./EmployeesManager";

export const dynamic = "force-dynamic";

/**
 * Employees: the people the owner or the admin adds here, and the orders
 * each one did. A row is someone's when its «Կատարող» column holds their
 * name; the figures by day, week, month… are on the orders page. Employees
 * may work in groups, each led by a manager (one manager, several groups).
 */
export default async function AdminEmployeesPage() {
  await requirePermission("settings");
  const [employees, groups] = await Promise.all([getEmployees(), getEmployeeGroups()]);

  return (
    <div className="space-y-8">
      <PageHeader
        title="Աշխատակիցներ"
        description="Ավելացրեք ձեր աշխատակիցներին, և պատվերների աղյուսակի «Կատարող» դաշտում ընտրեք, թե ով է կատարել պատվերը։ Աշխատակցի անունին սեղմելով կտեսնեք նրա պատվերները։"
      />

      <NewEmployeeForm groups={groups} />

      <SectionCard title={`Ցանկ (${employees.length})`}>
        <EmployeesList employees={employees} groups={groups} />
      </SectionCard>

      <SectionCard
        title={`Խմբեր և մենեջերներ (${groups.length})`}
        description="Միավորեք աշխատակիցներին խմբերում և նշանակեք մենեջեր։ Մեկ մենեջերը կարող է ղեկավարել մի քանի խումբ։ Աշխատակցին խումբ տվեք ցանկում՝ խմբագրելով։"
      >
        <GroupsManager groups={groups} employees={employees} />
      </SectionCard>
    </div>
  );
}
