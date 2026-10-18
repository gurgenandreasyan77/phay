import { redirect } from "next/navigation";

/** The tables are the orders section itself now. */
export default function AdminOrderTablesPage() {
  redirect("/admin/orders");
}
