import { ImportView } from "../../../../_components/tables/views";

export const dynamic = "force-dynamic";

export default async function TableImportPage(props: { params: Promise<{ id: string }>; searchParams: Promise<Record<string, string | string[] | undefined>> }) {
  const [{ id }, searchParams] = await Promise.all([props.params, props.searchParams]);
  return <ImportView section="sales" id={id} searchParams={searchParams} />;
}
