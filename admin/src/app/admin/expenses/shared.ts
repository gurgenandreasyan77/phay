// Shared by the expenses screens.
import type { RecurringFrequency } from "@/lib/adminApi";
import { validationErrors } from "../_components/tables/shared";

export const FREQUENCIES: RecurringFrequency[] = ["week", "month", "quarter", "year"];

export const FREQUENCY_LABELS: Record<RecurringFrequency, string> = {
  week: "Ամեն շաբաթ",
  month: "Ամեն ամիս",
  quarter: "Ամեն եռամսյակ",
  year: "Ամեն տարի",
};

/** Why a ready-made kind is or isn't in the profit, shown next to it. */
export const CATEGORY_HINTS: Record<string, string> = {
  purchases:
    "Վաճառքի համար գնված ապրանք։ Եթե վաճառքների աղյուսակում լրացնում եք ինքնարժեքը, անջատեք այս տեսակը շահույթից, որ նույն գումարը երկու անգամ չհանվի։",
  equipment: "Սարքավորումը ներդրում է, ոչ թե ամսական ծախս․ շահույթից դուրս է, բայց հաշվվում է «Մնացած գումարում»։",
  loan: "Վարկի մայր գումարի մարումը ծախս չէ․ տոկոսները կարող եք գրել «Բանկ, հաշվապահ, ծրագրեր» տեսակում։",
  owner: "Սեփականատիրոջ վերցրած գումարը շահույթից է, ոչ թե ծախս։",
};

export async function send(url: string, method: string, body?: unknown): Promise<{ ok: boolean; errors: Record<string, string> }> {
  const response = await fetch(url, {
    method,
    headers: { "Content-Type": "application/json" },
    body: body === undefined ? undefined : JSON.stringify(body),
  }).catch(() => null);
  if (!response) return { ok: false, errors: {} };
  if (response.status === 422) return { ok: false, errors: validationErrors(await response.json().catch(() => null)) };
  return { ok: response.ok, errors: {} };
}
