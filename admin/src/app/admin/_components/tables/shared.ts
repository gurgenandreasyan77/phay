// Shared by server and client components of the business's own tables.
import type { AdminDataColumnDto, BusinessPermission, ColumnRole, DataCellValue, DataColumnType, DataTableSection } from "@/lib/adminApi";

type SectionInfo = {
  label: string;
  base: string;
  /** Who reads and fills its tables. */
  permission: BusinessPermission;
  /** Who designs its tables (name, columns). */
  design: BusinessPermission;
};

export const SECTIONS: Record<DataTableSection, SectionInfo> = {
  orders: { label: "Պատվերներ", base: "/admin/orders", permission: "orders", design: "catalog" },
  sales: { label: "Վաճառքներ", base: "/admin/sales", permission: "orders", design: "catalog" },
  expenses: { label: "Ծախսեր", base: "/admin/expenses", permission: "expenses", design: "expenses" },
};

export function tablePath(section: DataTableSection, id?: number, suffix = ""): string {
  return id === undefined ? `${SECTIONS[section].base}/tables/new` : `${SECTIONS[section].base}/tables/${id}${suffix}`;
}

export function tablesPath(section: DataTableSection): string {
  return SECTIONS[section].base;
}

export const COLUMN_TYPES: DataColumnType[] = ["text", "number", "money", "date", "datetime", "select", "boolean"];

export const COLUMN_TYPE_LABELS: Record<DataColumnType, string> = {
  text: "Տեքստ",
  number: "Թիվ",
  money: "Գումար (֏)",
  date: "Ամսաթիվ",
  datetime: "Ամսաթիվ և ժամ",
  select: "Ցանկից ընտրություն",
  boolean: "Այո / Ոչ",
};

type RoleGroup = "when" | "what" | "money" | "how" | "buyer";

type RoleInfo = {
  /** The column's name in Armenian, Russian and English. */
  labels: { hy: string; ru: string; en: string };
  /** Types the role allows; the first is the default. */
  types: DataColumnType[];
  options?: string[];
  hint: string;
  group: RoleGroup;
};

/** The groups of the "what is this column" list, in its order. */
export const ROLE_GROUPS: { id: RoleGroup; label: string }[] = [
  { id: "when", label: "Երբ" },
  { id: "what", label: "Ինչ (ապրանք, ծառայություն)" },
  { id: "money", label: "Քանակ և գումար" },
  { id: "how", label: "Ինչպես, ով, որտեղ" },
  { id: "buyer", label: "Գնորդ" },
];

/**
 * Ready-made columns: what a column means for analytics, whatever it's
 * called. Mirrors the API's ColumnRole (labels, types, default options).
 */
export const COLUMN_ROLES: Record<ColumnRole, RoleInfo> = {
  date: {
    labels: { hy: "Ամսաթիվ", ru: "Дата", en: "Date" },
    types: ["datetime", "date"],
    hint: "Երբ է եղել պատվերը կամ վաճառքը",
    group: "when",
  },
  time: {
    labels: { hy: "Ժամ", ru: "Время", en: "Time" },
    types: ["text"],
    hint: "Ժամը առանձին սյունակում (14:30)․ միանում է ամսաթվին՝ օրացույցի և «Ըստ ժամերի» համար",
    group: "when",
  },
  duration: {
    labels: { hy: "Տևողություն (րոպե)", ru: "Длительность (мин)", en: "Duration (min)" },
    types: ["number"],
    hint: "Քանի րոպե տևեց ծառայությունը․ վերլուծությունում՝ միջին տևողություն և աշխատած ժամեր",
    group: "when",
  },
  item: {
    labels: { hy: "Ապրանք", ru: "Товար", en: "Product" },
    types: ["text", "select"],
    hint: "Ինչ է վաճառվել կամ արվել",
    group: "what",
  },
  category: {
    labels: { hy: "Կատեգորիա", ru: "Категория", en: "Category" },
    types: ["text", "select"],
    hint: "Ապրանքի խումբը․ պատվերներում՝ ծառայության տեսակը (սանրվածք, ներկում…), ծախսերում՝ ծախսի տեսակը",
    group: "what",
  },
  model: {
    labels: { hy: "Մոդել", ru: "Модель", en: "Model" },
    types: ["text", "select"],
    hint: "Ապրանքի մոդելը (օր.՝ iPhone 15, Galaxy A54)․ վերլուծությունում՝ ըստ մոդելների",
    group: "what",
  },
  brand: {
    labels: { hy: "Ապրանքանիշ", ru: "Бренд", en: "Brand" },
    types: ["text", "select"],
    hint: "Բրենդ կամ արտադրող",
    group: "what",
  },
  sku: {
    labels: { hy: "Ապրանքի կոդ", ru: "Артикул", en: "SKU" },
    types: ["text"],
    hint: "Արտիկուլ կամ շտրիխ-կոդ",
    group: "what",
  },
  country: {
    labels: { hy: "Արտադրող երկիր", ru: "Страна", en: "Country of origin" },
    types: ["text", "select"],
    hint: "Որտեղից է ներմուծված ապրանքը",
    group: "what",
  },
  material: {
    labels: { hy: "Նյութ", ru: "Материал", en: "Material" },
    types: ["text", "select"],
    hint: "Ինչից է պատրաստված (կաշի, սիլիկոն…)",
    group: "what",
  },
  color: {
    labels: { hy: "Գույն", ru: "Цвет", en: "Color" },
    types: ["text", "select"],
    hint: "Ապրանքի գույնը",
    group: "what",
  },
  size: {
    labels: { hy: "Չափս", ru: "Размер", en: "Size" },
    types: ["text", "select"],
    hint: "S, M, L, 42…",
    group: "what",
  },
  quantity: {
    labels: { hy: "Քանակ", ru: "Количество", en: "Quantity" },
    types: ["number"],
    hint: "Քանի հատ, կգ, լ… (0,5 կգ)",
    group: "money",
  },
  unit: {
    labels: { hy: "Չափման միավոր", ru: "Единица", en: "Unit" },
    types: ["select", "text"],
    options: ["հատ", "կգ", "գ", "լ", "մ", "մ²"],
    hint: "Քանակի միավորը",
    group: "money",
  },
  unit_price: {
    labels: { hy: "Գին", ru: "Цена", en: "Price" },
    types: ["money", "number"],
    hint: "Մեկ միավորի գինը․ առանց «Գումար» սյունակի՝ գումար = գին × քանակ − զեղչ + առաքում",
    group: "money",
  },
  amount: {
    labels: { hy: "Գումար", ru: "Сумма", en: "Total" },
    types: ["money", "number"],
    hint: "Տողի վերջնական գումարը (զեղչից հետո)․ «Վերջնական գին», «Итоговая цена» սյունակներն էլ են սա",
    group: "money",
  },
  discount: {
    labels: { hy: "Զեղչ", ru: "Скидка", en: "Discount" },
    types: ["money", "number"],
    hint: "Զեղչի գումարը դրամով․ վերլուծությունում՝ «Զեղչեր»",
    group: "money",
  },
  discount_percent: {
    labels: { hy: "Զեղչ, %", ru: "Скидка, %", en: "Discount, %" },
    types: ["number"],
    hint: "Զեղչը տոկոսով (10 կամ 10%)",
    group: "money",
  },
  cost: {
    labels: { hy: "Ինքնարժեք", ru: "Себестоимость", en: "Unit cost" },
    types: ["money", "number"],
    hint: "Մեկ միավորի ինքնարժեքը․ շահույթ = գումար − ինքնարժեք × քանակ − միջնորդավճար",
    group: "money",
  },
  commission: {
    labels: { hy: "Միջնորդավճար", ru: "Комиссия", en: "Commission" },
    types: ["money", "number"],
    hint: "Միջնորդին վճարված գումարը․ հանվում է շահույթից",
    group: "money",
  },
  delivery_fee: {
    labels: { hy: "Առաքման վճար", ru: "Стоимость доставки", en: "Delivery fee" },
    types: ["money", "number"],
    hint: "Առաքման արժեքը, որը վճարում է հաճախորդը",
    group: "money",
  },
  tax: {
    labels: { hy: "Հարկ (ԱԱՀ)", ru: "Налог (НДС)", en: "Tax (VAT)" },
    types: ["money", "number"],
    hint: "Գումարի մեջ ներառված հարկը",
    group: "money",
  },
  payment: {
    labels: { hy: "Վճարման եղանակ", ru: "Способ оплаты", en: "Payment method" },
    types: ["select", "text"],
    options: ["Կանխիկ", "Քարտ", "Փոխանցում"],
    hint: "Կանխիկ, քարտ…",
    group: "how",
  },
  channel: {
    labels: { hy: "Աղբյուր", ru: "Канал", en: "Channel" },
    types: ["select", "text"],
    options: ["Instagram", "Facebook", "Կայք", "Հեռախոս", "Խանութ"],
    hint: "Որտեղից է եկել հաճախորդը",
    group: "how",
  },
  status: {
    labels: { hy: "Կարգավիճակ", ru: "Статус", en: "Status" },
    types: ["select", "text"],
    options: ["Նոր", "Կատարված", "Չեղարկված"],
    hint: "Նոր, կատարված…",
    group: "how",
  },
  delivery_method: {
    labels: { hy: "Առաքման եղանակ", ru: "Способ доставки", en: "Delivery method" },
    types: ["select", "text"],
    options: ["Առաքում", "Ինքնատար", "Փոստ"],
    hint: "Առաքում, ինքնատար…",
    group: "how",
  },
  performer: {
    labels: { hy: "Կատարող", ru: "Исполнитель", en: "Performer" },
    types: ["text", "select"],
    hint: "Աշխատակիցը, ով կատարել կամ վաճառել է․ ընտրեք «Աշխատակիցներ» ցանկից",
    group: "how",
  },
  intermediary: {
    labels: { hy: "Միջնորդ", ru: "Посредник", en: "Intermediary" },
    types: ["text", "select"],
    hint: "Միջնորդ, գործակալ, մենթոր կամ գործընկեր, ով բերել է վաճառքը",
    group: "how",
  },
  supplier: {
    labels: { hy: "Մատակարար", ru: "Поставщик", en: "Supplier" },
    types: ["text", "select"],
    hint: "Ումից է գնված ապրանքը․ ծախսերում՝ ում է վճարվել",
    group: "how",
  },
  city: {
    labels: { hy: "Քաղաք", ru: "Город", en: "City" },
    types: ["text", "select"],
    hint: "Քաղաք, մարզ կամ թաղամաս․ վերլուծությունում՝ ըստ քաղաքների",
    group: "how",
  },
  branch: {
    labels: { hy: "Մասնաճյուղ", ru: "Филиал", en: "Branch" },
    types: ["text", "select"],
    hint: "Որ մասնաճյուղում կամ կետում",
    group: "how",
  },
  project: {
    labels: { hy: "Օբյեկտ", ru: "Объект", en: "Project" },
    types: ["text", "select"],
    hint: "Որ օբյեկտի կամ նախագծի համար (օր.՝ մաքրվող բնակարանը)",
    group: "how",
  },
  order_number: {
    labels: { hy: "Պատվերի համար", ru: "Номер заказа", en: "Order No." },
    types: ["text"],
    hint: "Պատվերի, կտրոնի կամ փաստաթղթի համարը",
    group: "how",
  },
  gender: {
    labels: { hy: "Սեռ", ru: "Пол", en: "Gender" },
    types: ["select", "text"],
    options: ["Արական", "Իգական"],
    hint: "Գնորդի սեռը․ վերլուծությունում ցույց է տրվում միայն տոկոսներով",
    group: "buyer",
  },
  age: {
    labels: { hy: "Տարիք", ru: "Возраст", en: "Age" },
    types: ["number", "select", "text"],
    hint: "Գնորդի տարիքը կամ խումբը (25-34)․ վերլուծությունում՝ միայն տարիքային խմբերով",
    group: "buyer",
  },
  customer: {
    labels: { hy: "Հաճախորդ", ru: "Клиент", en: "Customer" },
    types: ["text", "select"],
    hint: "Չի ուղարկվում AI-ին",
    group: "buyer",
  },
  phone: {
    labels: { hy: "Հեռախոս", ru: "Телефон", en: "Phone" },
    types: ["text"],
    hint: "Չի ուղարկվում AI-ին",
    group: "buyer",
  },
  email: {
    labels: { hy: "Էլ. փոստ", ru: "E-mail", en: "Email" },
    types: ["text"],
    hint: "Չի ուղարկվում AI-ին",
    group: "buyer",
  },
  address: {
    labels: { hy: "Հասցե", ru: "Адрес", en: "Address" },
    types: ["text"],
    hint: "Առաքման կամ աշխատանքի հասցեն․ չի ուղարկվում AI-ին",
    group: "buyer",
  },
  note: {
    labels: { hy: "Մեկնաբանություն", ru: "Комментарий", en: "Note" },
    types: ["text"],
    hint: "Ազատ տեքստ",
    group: "buyer",
  },
};

/** A role's labels by section: the item is a service in orders, what was paid for in expenses… */
export function roleLabels(role: ColumnRole, section: DataTableSection): RoleInfo["labels"] {
  if (role === "item" && section === "orders") return { hy: "Ծառայություն", ru: "Услуга", en: "Service" };
  if (role === "item" && section === "expenses") return { hy: "Ինչի համար", ru: "За что", en: "Description" };
  if (role === "category" && section === "orders") return { hy: "Ծառայության տեսակ", ru: "Вид услуги", en: "Service type" };
  if (role === "category" && section === "expenses") return { hy: "Ծախսի տեսակ", ru: "Категория расхода", en: "Expense category" };
  if (role === "performer" && section === "sales") return { hy: "Վաճառող", ru: "Продавец", en: "Seller" };
  if (role === "performer" && section === "expenses") return { hy: "Ով է ծախսել", ru: "Кто потратил", en: "Spent by" };
  if (role === "supplier" && section === "expenses") return { hy: "Ում է վճարվել", ru: "Получатель", en: "Payee" };
  if (role === "order_number" && section === "expenses") return { hy: "Փաստաթղթի համար", ru: "Номер документа", en: "Document No." };
  return COLUMN_ROLES[role].labels;
}

export const ROLE_ORDER = Object.keys(COLUMN_ROLES) as ColumnRole[];

/** How a stored value reads in the table. */
export function formatCell(value: DataCellValue | undefined, column: Pick<AdminDataColumnDto, "type">): string {
  if (value === null || value === undefined || value === "") return "—";

  switch (column.type) {
    case "money":
      return typeof value === "number" ? formatMoney(value) : String(value);
    case "number":
      return typeof value === "number" ? formatNumber(value) : String(value);
    case "date":
    case "datetime":
      return formatMoment(String(value));
    case "boolean":
      return value === true ? "Այո" : value === false ? "Ոչ" : String(value);
    default:
      return String(value);
  }
}

/** "2026-10-17" → "17.10.2026", "2026-10-17 14:35" → "17.10.2026 14:35". */
export function formatMoment(value: string): string {
  const match = /^(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{2}:\d{2}))?/.exec(value);
  if (!match) return value;
  const date = `${match[3]}.${match[2]}.${match[1]}`;
  return match[4] && match[4] !== "00:00" ? `${date} ${match[4]}` : date;
}

/**
 * An ISO time as "17.10.2026 18:27" in Yerevan time. Not toLocaleString():
 * the server and the browser format it differently, which breaks hydration.
 */
export function formatDateTime(iso: string | null | undefined): string {
  if (!iso) return "—";
  const parts = Object.fromEntries(
    new Intl.DateTimeFormat("en-GB", {
      timeZone: "Asia/Yerevan",
      year: "numeric",
      month: "2-digit",
      day: "2-digit",
      hour: "2-digit",
      minute: "2-digit",
      hour12: false,
    })
      .formatToParts(new Date(iso))
      .map((part) => [part.type, part.value]),
  );
  return `${parts.day}.${parts.month}.${parts.year} ${parts.hour}:${parts.minute}`;
}

/**
 * 12500.5 → "12 500,5", the Armenian way. By hand: Node on the server may
 * lack the hy-AM locale and print "12,500.5", which breaks hydration.
 */
export function formatNumber(value: number): string {
  const [whole, fraction] = String(Math.round(value * 100) / 100).split(".");
  const grouped = whole.replace(/\B(?=(\d{3})+(?!\d))/g, "\u00a0");
  return fraction ? `${grouped},${fraction}` : grouped;
}

export function formatMoney(value: number | null | undefined): string {
  return value === null || value === undefined ? "—" : `${formatNumber(value)} ֏`;
}

/** Laravel's 422 body: the first message per field. */
export function validationErrors(json: unknown): Record<string, string> {
  const errors = (json as { errors?: Record<string, string[]> } | null)?.errors ?? {};
  return Object.fromEntries(Object.entries(errors).map(([field, messages]) => [field, messages[0] ?? ""]));
}
