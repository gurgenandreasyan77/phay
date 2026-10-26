import Link from "next/link";
import { Bot, Users } from "lucide-react";
import { requirePermission } from "@/lib/adminAuth";
import { getAgents, getEmployeeGroups, getEmployees } from "@/lib/adminApi";
import { EmptyCard, PageHeader, SectionCard, cn } from "@/ui";
import { AgentCard } from "../_components/agents/AgentCard";
import { BrandAssets } from "../_components/agents/BrandAssets";
import { BrandProfileForm } from "../_components/agents/BrandProfileForm";
import { EmployeesList, GroupsManager, NewEmployeeForm } from "./EmployeesManager";

export const dynamic = "force-dynamic";

/**
 * Employees: the people the owner or the admin adds here, and the orders
 * each one did. A row is someone's when its «Կատարող» column holds their
 * name; the figures by day, week, month… are on the orders page. Employees
 * may work in groups, each led by a manager (one manager, several groups).
 *
 * ?tab=agents: the AI staff next to the people: the agents the business
 * has and the ones it can add, and the brand profile they learn from.
 */
export default async function AdminEmployeesPage(props: PageProps<"/admin/employees">) {
  await requirePermission("settings");
  const { tab } = await props.searchParams;
  const agentsTab = tab === "agents";

  return (
    <div className="space-y-8">
      <PageHeader
        title="Աշխատակիցներ"
        description={
          agentsTab
            ? "AI աշխատակիցները աշխատում են ձեր թիմի կողքին. խորհուրդ են տալիս, գրում են տեքստեր, սցենարներ և պլաններ՝ սովորելով հենց ձեր բիզնեսը։"
            : "Ավելացրեք ձեր աշխատակիցներին, և պատվերների աղյուսակի «Կատարող» դաշտում ընտրեք, թե ով է կատարել պատվերը։ Աշխատակցի անունին սեղմելով կտեսնեք նրա պատվերները։"
        }
      />

      <nav className="segmented inline-flex rounded-lg border border-border bg-surface p-1" aria-label="Աշխատակիցների տեսակները">
        {[
          {
            href: "/admin/employees",
            label: "Մարդիկ",
            icon: Users,
            current: !agentsTab,
          },
          {
            href: "/admin/employees?tab=agents",
            label: "AI աշխատակիցներ",
            icon: Bot,
            current: agentsTab,
          },
        ].map((item) => (
          <Link
            key={item.href}
            href={item.href}
            aria-current={item.current ? "page" : undefined}
            className={cn(
              "flex items-center gap-2 rounded-md px-3 py-1.5 text-sm transition-colors",
              item.current ? "bg-primary text-primary-foreground" : "text-ink-muted hover:bg-surface-secondary hover:text-ink",
            )}
          >
            <item.icon className="h-4 w-4" aria-hidden="true" />
            {item.label}
          </Link>
        ))}
      </nav>

      {agentsTab ? <AgentsTab /> : <PeopleTab />}
    </div>
  );
}

async function PeopleTab() {
  const [employees, groups] = await Promise.all([getEmployees(), getEmployeeGroups()]);

  return (
    <>
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
    </>
  );
}

async function AgentsTab() {
  const team = await getAgents();

  if (!team) {
    return <EmptyCard>Չհաջողվեց բեռնել AI աշխատակիցներին։ Թարմացրեք էջը։</EmptyCard>;
  }

  const active = team.agents.filter((a) => a.active);
  const waiting = team.agents.filter((a) => !a.active);
  // Who to tell about the business in the chat: an agent that needs the brand, else any.
  const teacher = team.canChat ? (active.find((a) => a.needsBrand) ?? active[0] ?? null) : null;

  return (
    <>
      <SectionCard
        title={`Ձեր AI թիմը (${active.length})`}
        description={
          active.length > 0
            ? "Նրանք արդեն աշխատում են ձեզ համար։ Յուրաքանչյուր հարց նրանց օգտագործում է AI օգնականի ամսական հարցերից մեկը։"
            : "Թիմում դեռ AI աշխատակիցներ չկան։ Ընտրեք ներքևից, թե ով է ձեզ պետք, և ուղարկեք հայտ։"
        }
      >
        {active.length > 0 ? (
          <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            {active.map((agent, index) => (
              <AgentCard key={agent.key} agent={agent} canChat={team.canChat} canRequest={team.canRequest} index={index} />
            ))}
          </div>
        ) : (
          <EmptyCard>Դեռ ոչ ոք։</EmptyCard>
        )}
      </SectionCard>

      <div id="brand" className="scroll-mt-20">
        <SectionCard
          title="Ուսուցում՝ ձեր բրենդը"
          description="Այն, ինչ AI թիմը գիտի ձեր բիզնեսի մասին։ Որքան մանրամասն, այնքան ավելի ճշգրիտ կլինեն տեքստերը, սցենարներն ու նկարների բրիֆները։ Տվյալները տեսանելի են միայն ձեր բիզնեսի թիմին։"
        >
          <div className="space-y-6">
            <BrandAssets assets={team.assets ?? []} />
            <BrandProfileForm profile={team.profile} teacher={teacher} />
          </div>
        </SectionCard>
      </div>

      {waiting.length > 0 ? (
        <SectionCard
          title="Կարող են միանալ ձեր թիմին"
          description="Հայտը կուղարկվի Phay-ի թիմին, և աշխատակիցը կմիանա ձեր թիմին միացնելուց հետո։"
        >
          <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            {waiting.map((agent, index) => (
              <AgentCard key={agent.key} agent={agent} canChat={team.canChat} canRequest={team.canRequest} index={index} />
            ))}
          </div>
        </SectionCard>
      ) : null}
    </>
  );
}
