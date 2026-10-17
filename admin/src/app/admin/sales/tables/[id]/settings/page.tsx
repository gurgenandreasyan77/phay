import { TableSettingsView } from "../../../../_components/tables/views";

export const dynamic = "force-dynamic";

export default async function TableSettingsPage(props: { params: Promise<{ id: string }> }) {
  const { id } = await props.params;
  return <TableSettingsView section="sales" id={id} />;
}
