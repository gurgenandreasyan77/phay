import Link from "next/link";
import { requireSuperAdmin } from "@/lib/adminAuth";
import { getSecurityEvents } from "@/lib/adminApi";
import { EmptyRow, Input, PageHeader, Select, TableWrap, Td, Th, Thead, Tr, buttonClasses, cn } from "../../_components/ui";
import { dateTime, deviceLabel } from "../../_components/auth/format";

export const dynamic = "force-dynamic";

/**
 * The platform's security log (super admin): sign-ins and failed attempts,
 * 2FA changes, password resets, invitations, removed logins. Failed and
 * suspicious events are in red; "Միայն զգուշացումներ" shows only those.
 */
export default async function PlatformSecurityPage({
  searchParams,
}: {
  searchParams: Promise<{ event?: string; email?: string; warnings?: string; page?: string }>;
}) {
  await requireSuperAdmin();
  const params = await searchParams;
  const query = new URLSearchParams();
  if (params.event) query.set("event", params.event);
  if (params.email) query.set("email", params.email);
  if (params.warnings === "1") query.set("warnings", "1");
  if (params.page) query.set("page", params.page);
  const page = await getSecurityEvents(query.size ? `?${query.toString()}` : "");

  const pageLink = (n: number) => {
    const next = new URLSearchParams(query);
    next.set("page", String(n));
    return `/admin/platform/security?${next.toString()}`;
  };

  return (
    <div className="space-y-6">
      <PageHeader
        title="Անվտանգության մատյան"
        description="Ով, երբ և որտեղից է մուտք գործել, անհաջող փորձեր, 2FA-ի և գաղտնաբառերի փոփոխություններ, հրավերներ։ Գրառումները չեն խմբագրվում։"
      />

      <form className="flex flex-wrap items-end gap-3" action="/admin/platform/security">
        <div className="min-w-56 space-y-1">
          <p className="text-xs text-ink-muted">Էլ. փոստ</p>
          <Input name="email" defaultValue={params.email ?? ""} placeholder="name@example.am" />
        </div>
        <div className="space-y-1">
          <p className="text-xs text-ink-muted">Իրադարձություն</p>
          <Select name="event" defaultValue={params.event ?? ""}>
            <option value="">Բոլորը</option>
            {(page?.events ?? []).map((e) => (
              <option key={e.value} value={e.value}>
                {e.label}
              </option>
            ))}
          </Select>
        </div>
        <label className="flex items-center gap-2 pb-2 text-sm text-ink">
          <input type="checkbox" name="warnings" value="1" defaultChecked={params.warnings === "1"} />
          Միայն զգուշացումներ
        </label>
        <button type="submit" className={buttonClasses("secondary")}>
          Ցույց տալ
        </button>
      </form>

      <TableWrap>
        <Thead>
          <Th>Երբ</Th>
          <Th>Ինչ</Th>
          <Th>Ով</Th>
          <Th>Ով արեց</Th>
          <Th>IP · սարք</Th>
        </Thead>
        <tbody>
          {(page?.data ?? []).map((event) => (
            <Tr key={event.id}>
              <Td className="whitespace-nowrap text-xs">{dateTime(event.createdAt)}</Td>
              <Td>
                <span className={cn(event.warning && "font-medium text-danger")}>{event.label}</span>
              </Td>
              <Td className="text-xs">
                {event.user?.name ? <span className="block text-ink">{event.user.name}</span> : null}
                <span className="font-mono text-ink-muted">{event.email ?? "—"}</span>
              </Td>
              <Td className="text-xs text-ink-muted">{event.actor?.name ?? "—"}</Td>
              <Td className="text-xs text-ink-muted">
                <span className="font-mono">{event.ipAddress ?? "—"}</span>
                <span className="block">{event.userAgent ? deviceLabel(event.userAgent) : "կոնսոլ"}</span>
              </Td>
            </Tr>
          ))}
          {page && page.data.length === 0 ? <EmptyRow colSpan={5}>Գրառումներ չկան։</EmptyRow> : null}
          {!page ? <EmptyRow colSpan={5}>Չհաջողվեց բեռնել մատյանը։</EmptyRow> : null}
        </tbody>
      </TableWrap>

      {page && page.meta.lastPage > 1 ? (
        <div className="flex items-center gap-3 text-sm">
          {page.meta.currentPage > 1 ? <Link href={pageLink(page.meta.currentPage - 1)}>← Նախորդ</Link> : null}
          <span className="text-ink-muted">
            {page.meta.currentPage} / {page.meta.lastPage}
          </span>
          {page.meta.currentPage < page.meta.lastPage ? <Link href={pageLink(page.meta.currentPage + 1)}>Հաջորդ →</Link> : null}
        </div>
      ) : null}
    </div>
  );
}
