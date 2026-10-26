import { redirect } from "next/navigation";

/** The market module has no page of its own: its first screen is the shop. */
export default function AdminMarketPage() {
  redirect("/admin/products");
}
