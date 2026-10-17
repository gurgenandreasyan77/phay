import { TablesListView } from "../_components/tables/views";

export const dynamic = "force-dynamic";

/** The sales module: the tables a business that sells goods keeps its sales in. */
export default function AdminSalesPage() {
  return <TablesListView section="sales" />;
}
