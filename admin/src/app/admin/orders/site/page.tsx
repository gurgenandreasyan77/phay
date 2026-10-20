import Link from "next/link";
import { requirePermission } from "@/lib/adminAuth";
import { getLeads } from "@/lib/adminApi";
import { StatusFilter } from "./StatusFilter";
import { SearchBox } from "./SearchBox";
import { OrderStatusSelect } from "./OrderStatusSelect";
import { OrderPriceInput } from "./OrderPriceInput";
import { LEAD_STATUS_LABELS } from "../../_components/leadStatus";
import { Badge, buttonClasses, EmptyRow, PageHeader, TableWrap, Th, Thead, Td, Tr } from "../../_components/ui";

export const dynamic = "force-dynamic";

function first(value: string | string[] | undefined): string | undefined {
  return Array.isArray(value) ? value[0] : value;
}

/** Orders from the site's form: a table of their own, kept by status and final price. */
export default async function AdminSiteOrdersPage(props: PageProps<"/admin/orders/site">) {
  await requirePermission("orders");

  const searchParams = await props.searchParams;
  const status = first(searchParams.status);
  const search = first(searchParams.search);

  const { items } = await getLeads({ status, search });

  return (
    <div className="space-y-6">
      <Link href="/admin/orders" className={buttonClasses("ghost", "sm")}>
        ← Բոլոր աղյուսակները
      </Link>
      <PageHeader
        title="Կայքից եկած պատվերներ"
        description="Կայքի ձևից եկած պատվերները։ Նշեք կարգավիճակը և վերջնական գինը, և վերլուծությունը դրանք կհաշվի ձեր աղյուսակների հետ։"
      />

      <div className="flex flex-wrap items-center gap-3">
        <StatusFilter statusLabels={LEAD_STATUS_LABELS} />
        <SearchBox />
      </div>

      <TableWrap>
        <Thead>
          <Th>Անուն</Th>
          <Th>Հեռախոս</Th>
          <Th>Ծառայություն/ապրանք</Th>
          <Th>Հասցե</Th>
          <Th>Աղբյուր</Th>
          <Th>Ամսաթիվ</Th>
          <Th>Կարգավիճակ</Th>
          <Th>Վերջնական գին</Th>
        </Thead>
        <tbody>
          {items.map((lead) => (
            <Tr key={lead.id}>
              <Td className="font-medium">{lead.name}</Td>
              <Td className="text-ink-muted">{lead.phone}</Td>
              <Td className="text-ink-muted" title={lead.comment ?? undefined}>
                {lead.service?.name ?? lead.package?.name ?? lead.product?.name ?? "—"}
                {lead.carBrand || lead.carModel ? (
                  <span className="block text-xs text-ink-faint">
                    {[lead.carBrand, lead.carModel].filter(Boolean).join(" ")}
                  </span>
                ) : null}
              </Td>
              <Td className="text-ink-muted">
                {lead.fulfillmentType ? (
                  <Badge tone={lead.fulfillmentType === "delivery" ? "info" : "default"} className="mr-1.5">
                    {lead.fulfillmentType === "delivery" ? "Առաքում" : "Ինքնուրույն վերցնել"}
                  </Badge>
                ) : null}
                {lead.address ?? (lead.fulfillmentType ? null : "—")}
              </Td>
              <Td className="text-ink-muted">{lead.source ?? "—"}</Td>
              <Td className="text-ink-muted">
                {lead.createdAt ? new Date(lead.createdAt).toLocaleDateString("hy-AM") : "—"}
              </Td>
              <Td>
                <OrderStatusSelect leadId={lead.id} status={lead.status} statusLabels={LEAD_STATUS_LABELS} />
              </Td>
              <Td>
                <OrderPriceInput
                  key={`${lead.id}-${lead.finalPrice}`}
                  leadId={lead.id}
                  finalPrice={lead.finalPrice}
                  placeholder={lead.estimatedPriceMin ? String(lead.estimatedPriceMin) : undefined}
                />
              </Td>
            </Tr>
          ))}
          {items.length === 0 ? <EmptyRow colSpan={8}>Պատվերներ չեն գտնվել։</EmptyRow> : null}
        </tbody>
      </TableWrap>
    </div>
  );
}
