// The catalog of the analytics screen: what each widget is called (in
// Armenian, Russian and English) and what a missing column is called.
import type { DashboardWidgetId, DataTableSection } from "@/lib/adminApi";

type Labels = { hy: string; ru: string; en: string };

export const WIDGET_GROUPS: { id: "figures" | "charts" | "tables"; label: string }[] = [
  { id: "figures", label: "Թվեր" },
  { id: "charts", label: "Գրաֆիկներ" },
  { id: "tables", label: "Աղյուսակներ և համեմատություններ" },
];

export function widgetLabels(id: DashboardWidgetId, section: DataTableSection): Labels & { hint: string } {
  const orders = section === "orders";
  switch (id) {
    case "revenue":
      return { hy: "Եկամուտ", ru: "Выручка", en: "Revenue", hint: "Բոլոր տողերի գումարը" };
    case "profit":
      return { hy: "Շահույթ", ru: "Прибыль", en: "Profit", hint: "Եկամուտ − ինքնարժեք × քանակ" };
    case "margin":
      return { hy: "Շահութաբերություն", ru: "Рентабельность", en: "Margin", hint: "Շահույթի բաժինը եկամտում, %" };
    case "count":
      return orders
        ? { hy: "Պատվերների քանակ", ru: "Число заказов", en: "Orders", hint: "Քանի պատվեր է եղել" }
        : { hy: "Վաճառքների քանակ", ru: "Число продаж", en: "Sales", hint: "Քանի վաճառք է եղել" };
    case "perDay":
      return { hy: "Միջինը օրական", ru: "В среднем в день", en: "Per day", hint: orders ? "Պատվերներ օրական" : "Վաճառքներ օրական" };
    case "perMonth":
      return { hy: "Միջինը ամսական", ru: "В среднем в месяц", en: "Per month", hint: orders ? "Պատվերներ ամսական" : "Վաճառքներ ամսական" };
    case "averageCheck":
      return { hy: "Միջին չեկ", ru: "Средний чек", en: "Average check", hint: "Մեկ տողի միջին գումարը" };
    case "units":
      return { hy: "Քանակ ըստ միավորի", ru: "Количество по единицам", en: "Quantity by unit", hint: "կգ, հատ…՝ ընդամենը, միջինը, մեկ միավորի գինը" };
    case "deductions":
      return { hy: "Զեղչեր և միջնորդավճարներ", ru: "Скидки и комиссии", en: "Discounts and commissions", hint: "Զեղչեր, միջնորդավճարներ, առաքման վճարներ, հարկ" };
    case "duration":
      return { hy: "Տևողություն", ru: "Длительность", en: "Duration", hint: "Ծառայության միջին տևողությունը և աշխատած ժամերը" };
    case "revenueChart":
      return { hy: "Եկամուտ ըստ օրերի", ru: "Выручка по дням", en: "Revenue over time", hint: "Ըստ օրերի, տարվա համար՝ ըստ ամիսների" };
    case "countChart":
      return orders
        ? { hy: "Պատվերներ ըստ օրերի", ru: "Заказы по дням", en: "Orders over time", hint: "Ըստ օրերի, տարվա համար՝ ըստ ամիսների" }
        : { hy: "Վաճառքներ ըստ օրերի", ru: "Продажи по дням", en: "Sales over time", hint: "Ըստ օրերի, տարվա համար՝ ըստ ամիսների" };
    case "profitChart":
      return { hy: "Շահույթ ըստ օրերի", ru: "Прибыль по дням", en: "Profit over time", hint: "Ըստ օրերի, տարվա համար՝ ըստ ամիսների" };
    case "weekdays":
      return { hy: "Ըստ շաբաթվա օրերի", ru: "По дням недели", en: "By weekday", hint: "Որ օրերին է ավելի շատ աշխատանք" };
    case "hours":
      return { hy: "Ըստ ժամերի", ru: "По часам", en: "By hour", hint: orders ? "Երբ են գալիս պատվերները" : "Երբ են լինում վաճառքները" };
    case "peaks":
      return { hy: "Լավագույն և ամենաթույլ օրը", ru: "Лучший и слабый период", en: "Best and weakest", hint: "Տարվա համար՝ ամիսները" };
    case "months":
      return { hy: "Տարվա ամիսները", ru: "Месяцы года", en: "Months of the year", hint: "Որ ամիսներն են եկամտաբեր, որոնք՝ ոչ" };
    case "byItem":
      return orders
        ? { hy: "Ըստ ծառայությունների", ru: "По услугам", en: "By service", hint: "Ինչն են ավելի շատ պատվիրում" }
        : { hy: "Ըստ ապրանքների", ru: "По товарам", en: "By product", hint: "Ինչն է ավելի շատ վաճառվում" };
    case "byPayment":
      return { hy: "Ըստ վճարման եղանակի", ru: "По способу оплаты", en: "By payment method", hint: "Կանխիկ, քարտ…" };
    case "byChannel":
      return { hy: "Ըստ աղբյուրի", ru: "По каналам", en: "By channel", hint: "Instagram, կայք…" };
    case "byStatus":
      return { hy: "Ըստ կարգավիճակի", ru: "По статусу", en: "By status", hint: "Նոր, կատարված…" };
    case "byDetails":
      return { hy: "Ըստ մոդելի, մատակարարի, միջնորդի…", ru: "По модели, поставщику, посреднику…", en: "By model, supplier, intermediary…", hint: "Մոդել, ապրանքանիշ, երկիր, նյութ, տեսակ, քաղաք և այլն" };
    case "byOwnColumns":
      return { hy: "Ըստ ձեր սյունակների", ru: "По вашим колонкам", en: "By your columns", hint: "Ձեր ցանկերի և այո/ոչ սյունակների արժեքները" };
    case "ownNumbers":
      return { hy: "Ձեր թվային սյունակները", ru: "Ваши числовые колонки", en: "Your number columns", hint: "Յուրաքանչյուրի ընդհանուր գումարը" };
    case "tables":
      return { hy: "Ըստ աղյուսակների", ru: "По таблицам", en: "By table", hint: "Յուրաքանչյուր աղյուսակի տողերը և գումարը" };
  }
}

/** What a widget needs, in the words of the table designer. */
export const MISSING_LABELS: Record<string, string> = {
  date: "«Ամսաթիվ» սյունակ",
  money: "«Գին» կամ «Գումար» սյունակ",
  cost: "«Ինքնարժեք» սյունակ",
  quantity: "«Քանակ» սյունակ",
  item: "«Ապրանք» կամ «Ծառայություն» սյունակ",
  payment: "«Վճարման եղանակ» սյունակ",
  channel: "«Աղբյուր» սյունակ",
  status: "«Կարգավիճակ» սյունակ",
  datetime: "«Ամսաթիվ և ժամ» տեսակի ամսաթիվ",
  ownColumns: "ձեր սեփական ցանկի կամ տեքստի սյունակ",
  details: "«Մոդել», «Մատակարար», «Միջնորդ», «Երկիր», «Կատեգորիա» կամ նման սյունակ",
  deductions: "«Զեղչ», «Միջնորդավճար», «Առաքման վճար» կամ «Հարկ» սյունակ",
  duration: "«Տևողություն» սյունակ",
  ownNumbers: "ձեր սեփական թվային սյունակ",
  manyTables: "առնվազն երկու աղյուսակ",
};
