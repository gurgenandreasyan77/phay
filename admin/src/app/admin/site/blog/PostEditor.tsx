"use client";

import { useState } from "react";
import { useRouter } from "next/navigation";
import type { AdminPostDto, AdminTranslated, PostCategory, PostStatus } from "@/lib/adminApi";
import { ImageUploadField } from "../../services/ImageUploadField";
import { LOCALES, TranslatedFields, emptyTranslated, fieldClasses, labelClasses, toTranslated } from "../../services/shared";
import { Button, buttonClasses } from "@/ui";
import { POST_CATEGORY_LABELS } from "./shared";

type Form = {
  slug: string;
  category: PostCategory;
  status: PostStatus;
  featuredImagePath: string;
  title: AdminTranslated;
  excerpt: AdminTranslated;
  content: AdminTranslated;
  seoTitle: AdminTranslated;
  seoDescription: AdminTranslated;
};

function formFrom(post: AdminPostDto | null): Form {
  return {
    slug: post?.slug ?? "",
    category: post?.category ?? "company-news",
    status: post?.status ?? "draft",
    featuredImagePath: post?.featuredImagePath ?? "",
    title: post ? toTranslated(post.title) : emptyTranslated(),
    excerpt: post ? toTranslated(post.excerpt) : emptyTranslated(),
    content: post ? toTranslated(post.content) : emptyTranslated(),
    seoTitle: post ? toTranslated(post.seoTitle) : emptyTranslated(),
    seoDescription: post ? toTranslated(post.seoDescription) : emptyTranslated(),
  };
}

/** Latin letters, digits and dashes, from the Russian or English title. */
function slugFrom(title: AdminTranslated): string {
  const source = (title.en || "").trim() || (title.ru || "").trim();
  const map: Record<string, string> = {
    а: "a", б: "b", в: "v", г: "g", д: "d", е: "e", ё: "e", ж: "zh", з: "z", и: "i", й: "y", к: "k", л: "l",
    м: "m", н: "n", о: "o", п: "p", р: "r", с: "s", т: "t", у: "u", ф: "f", х: "h", ц: "ts", ч: "ch", ш: "sh",
    щ: "sch", ъ: "", ы: "y", ь: "", э: "e", ю: "yu", я: "ya",
  };
  return source
    .toLowerCase()
    .split("")
    .map((c) => map[c] ?? c)
    .join("")
    .replace(/[^a-z0-9]+/g, "-")
    .replace(/^-+|-+$/g, "")
    .slice(0, 80);
}

type ApiErrors = Record<string, string[]>;

/**
 * Writes a blog post. The text is simple HTML: paragraphs <p>, headings
 * <h2>, lists, bold, links. The API keeps only that formatting.
 */
export function PostEditor({ post }: { post: AdminPostDto | null }) {
  const router = useRouter();
  const [form, setForm] = useState<Form>(() => formFrom(post));
  const [pending, setPending] = useState(false);
  const [errors, setErrors] = useState<ApiErrors | null>(null);
  const [failed, setFailed] = useState(false);

  const set = <K extends keyof Form>(key: K, value: Form[K]) => setForm((f) => ({ ...f, [key]: value }));

  async function save(e: React.FormEvent) {
    e.preventDefault();
    setPending(true);
    setErrors(null);
    setFailed(false);
    try {
      const response = await fetch(post ? `/api/admin/posts/${post.id}` : "/api/admin/posts", {
        method: post ? "PATCH" : "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          slug: form.slug.trim() || slugFrom(form.title),
          category: form.category,
          status: form.status,
          featured_image_path: form.featuredImagePath.trim() || null,
          title: form.title,
          excerpt: form.excerpt,
          content: form.content,
          seo_title: form.seoTitle,
          seo_description: form.seoDescription,
        }),
      });
      const json = (await response.json().catch(() => null)) as { data?: AdminPostDto; errors?: ApiErrors } | null;
      if (response.status === 422) {
        setErrors(json?.errors ?? {});
        return;
      }
      if (!response.ok || !json?.data) {
        setFailed(true);
        return;
      }
      if (post) {
        router.refresh();
      } else {
        router.push(`/admin/site/blog/${json.data.id}`);
      }
    } catch {
      setFailed(true);
    } finally {
      setPending(false);
    }
  }

  async function remove() {
    if (!post || !confirm("Ջնջե՞լ հոդվածը։")) return;
    setPending(true);
    const response = await fetch(`/api/admin/posts/${post.id}`, { method: "DELETE" });
    setPending(false);
    if (response.ok) router.push("/admin/site/blog");
    else setFailed(true);
  }

  const errorList = errors ? Object.values(errors).flat() : [];

  return (
    <form onSubmit={save} className="space-y-6">
      <div className="grid gap-3 sm:grid-cols-3">
        <label className="space-y-1.5">
          <span className={labelClasses}>Վիճակ</span>
          <select value={form.status} onChange={(e) => set("status", e.target.value as PostStatus)} className={fieldClasses}>
            <option value="draft">Սևագիր (միայն այստեղ)</option>
            <option value="published">Հրապարակված կայքում</option>
          </select>
        </label>
        <label className="space-y-1.5">
          <span className={labelClasses}>Թեմա</span>
          <select value={form.category} onChange={(e) => set("category", e.target.value as PostCategory)} className={fieldClasses}>
            {(Object.keys(POST_CATEGORY_LABELS) as PostCategory[]).map((c) => (
              <option key={c} value={c}>
                {POST_CATEGORY_LABELS[c]}
              </option>
            ))}
          </select>
        </label>
        <label className="space-y-1.5">
          <span className={labelClasses}>Հասցե (լատինատառ, ոչ պարտադիր)</span>
          <input
            value={form.slug}
            onChange={(e) => set("slug", e.target.value)}
            placeholder={slugFrom(form.title) || "sofa-care-tips"}
            className={fieldClasses}
          />
        </label>
      </div>

      <TranslatedFields label="Վերնագիր (հայերենը՝ պարտադիր)" value={form.title} onChange={(v) => set("title", v)} />
      <TranslatedFields label="Կարճ նկարագրություն" value={form.excerpt} onChange={(v) => set("excerpt", v)} multiline />

      <div className="space-y-1.5">
        <p className={labelClasses}>
          Տեքստ (հայերենը՝ պարտադիր).{" "}
          <span className="font-normal text-ink-faint">
            Պարբերությունը՝ &lt;p&gt;…&lt;/p&gt;, ենթավերնագիրը՝ &lt;h2&gt;…&lt;/h2&gt;, ցանկը՝ &lt;ul&gt;&lt;li&gt;…&lt;/li&gt;&lt;/ul&gt;, հղումը՝ &lt;a href=&quot;…&quot;&gt;…&lt;/a&gt;։
          </span>
        </p>
        <div className="grid gap-2 lg:grid-cols-3">
          {LOCALES.map(({ key, label }) => (
            <textarea
              key={key}
              value={form.content[key] ?? ""}
              placeholder={label}
              rows={14}
              onChange={(e) => set("content", { ...form.content, [key]: e.target.value })}
              className={`${fieldClasses} font-mono text-xs`}
            />
          ))}
        </div>
      </div>

      <ImageUploadField label="Գլխավոր նկար" value={form.featuredImagePath} onChange={(v) => set("featuredImagePath", v)} folder="posts" />

      <TranslatedFields label="SEO վերնագիր" value={form.seoTitle} onChange={(v) => set("seoTitle", v)} />
      <TranslatedFields label="SEO նկարագրություն" value={form.seoDescription} onChange={(v) => set("seoDescription", v)} multiline />

      {errorList.length > 0 ? (
        <ul className="list-inside list-disc text-sm text-danger">
          {errorList.map((message) => (
            <li key={message}>{message}</li>
          ))}
        </ul>
      ) : null}
      {failed ? <p className="text-sm text-danger">Չհաջողվեց պահպանել։</p> : null}

      <div className="flex flex-wrap items-center gap-3">
        <Button type="submit" disabled={pending}>
          {pending ? "Պահպանվում է…" : post ? "Պահպանել" : "Ստեղծել հոդվածը"}
        </Button>
        {post ? (
          <button type="button" onClick={remove} disabled={pending} className={buttonClasses("secondary")}>
            Ջնջել
          </button>
        ) : null}
      </div>
    </form>
  );
}
