import { redirect } from "next/navigation";

/** The expense tables are listed on the expenses page. */
export default function AdminExpenseTablesPage() {
  redirect("/admin/expenses");
}
