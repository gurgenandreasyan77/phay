import { TablesListView } from "../_components/tables/views";

export const dynamic = "force-dynamic";

/** The orders module: the business's order tables, and the site's orders as one of them. */
export default function AdminOrdersPage() {
  return <TablesListView section="orders" />;
}
