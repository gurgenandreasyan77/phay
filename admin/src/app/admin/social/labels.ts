// Plain data, shared by the server page and the client parts.

export const NETWORKS: Record<string, { label: string; color: string }> = {
  instagram: { label: "Instagram", color: "#d1477a" },
  facebook: { label: "Facebook", color: "#3b69c9" },
};

/** Meta's metric names, in the order they are shown. */
export const METRICS: Record<string, string> = {
  reach: "Ընդգրկում",
  views: "Դիտումներ",
  accounts_engaged: "Ներգրավված հաշիվներ",
  total_interactions: "Փոխազդեցություններ",
  likes: "Հավանումներ",
  comments: "Մեկնաբանություններ",
  shares: "Տարածումներ",
  saves: "Պահպանումներ",
  replies: "Պատասխաններ",
  profile_links_taps: "Սեղմումներ պրոֆիլի հղումներին",
  page_media_view: "Դիտումներ",
  page_total_media_view_unique: "Դիտողներ",
  page_post_engagements: "Գրառումների ներգրավվածություն",
  page_views_total: "Էջի դիտումներ",
  page_daily_follows_unique: "Նոր հետևորդներ",
  page_daily_unfollows_unique: "Դադարեցրել են հետևել",
};

export const POST_KINDS: Record<string, string> = {
  REELS: "Reels",
  IMAGE: "Նկար",
  VIDEO: "Տեսանյութ",
  CAROUSEL_ALBUM: "Կարուսել",
  post: "Գրառում",
  OTHER: "Այլ",
};

export const WEEKDAYS: Record<string, string> = {
  Monday: "Երկուշաբթի",
  Tuesday: "Երեքշաբթի",
  Wednesday: "Չորեքշաբթի",
  Thursday: "Հինգշաբթի",
  Friday: "Ուրբաթ",
  Saturday: "Շաբաթ",
  Sunday: "Կիրակի",
};

export const GENDERS: Record<string, string> = { F: "Կանայք", M: "Տղամարդիկ", U: "Չնշված" };

/** What came back from Meta's login page (?meta=…). */
export const NOTICES: Record<string, { text: string; tone: "success" | "danger" }> = {
  connected: { text: "Facebook-ը միացված է։ Ստուգեք, որ ընտրված են ձեր բիզնեսի էջն ու Instagram հաշիվը։", tone: "success" },
  denied: { text: "Facebook-ում թույլտվությունը չտրվեց, ոչինչ չի միացվել։", tone: "danger" },
  expired: { text: "Միացման հղումը ժամկետանց է։ Փորձեք նորից։", tone: "danger" },
  not_configured: { text: "Instagram-ի և Facebook-ի հետ կապը հարթակում դեռ կարգավորված չէ։", tone: "danger" },
  forbidden: { text: "Միացնել կարող է միայն բիզնեսի սեփականատերը կամ ադմինիստրատորը։", tone: "danger" },
  error: { text: "Չհաջողվեց կապվել Meta-ի հետ։ Փորձեք մի փոքր ուշ։", tone: "danger" },
};

/** Permissions Meta asks for, as the owner sees them on its page. */
export const PERMISSIONS: Record<string, string> = {
  pages_show_list: "էջերի ցանկը",
  pages_read_engagement: "էջի գրառումներն ու ներգրավվածությունը",
  instagram_basic: "Instagram պրոֆիլն ու գրառումները",
  instagram_manage_insights: "Instagram-ի վիճակագրությունը",
};
