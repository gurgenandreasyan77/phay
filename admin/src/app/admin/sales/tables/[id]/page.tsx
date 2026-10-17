import { TableView } from "../../../_components/tables/views";

export const dynamic = "force-dynamic";

export default async function TablePage(props: { params: Promise<{ id: string }>; searchParams: Promise<Record<string, string | string[] | undefined>> }) {
  const [{ id }, searchParams] = await Promise.all([props.params, props.searchParams]);
  return <TableView section="sales" id={id} searchParams={searchParams} />;
}
