"use client";

import type { AdminFaqList, AdminTranslated, AdminTranslatedList } from "@/lib/adminApi";

export type Locale = "hy" | "ru" | "en";
export const LOCALES: { key: Locale; label: string }[] = [
  { key: "hy", label: "Հայերեն" },
  { key: "ru", label: "Ռուսերեն" },
  { key: "en", label: "Անգլերեն" },
];

export const fieldClasses =
  "w-full rounded-md border border-border bg-surface px-2.5 py-1.5 text-sm text-ink placeholder:text-ink-faint transition-colors focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary-light disabled:cursor-not-allowed disabled:bg-surface-secondary disabled:text-ink-faint";
export const labelClasses = "text-xs font-medium text-ink-muted";

export function emptyTranslated(): AdminTranslated {
  return { hy: "", ru: "", en: "" };
}

export function emptyTranslatedList(): AdminTranslatedList {
  return { hy: [], ru: [], en: [] };
}

export function toTranslated(value: AdminTranslated | undefined): AdminTranslated {
  return { hy: value?.hy ?? "", ru: value?.ru ?? "", en: value?.en ?? "" };
}

export function listToLines(value: AdminTranslatedList | undefined, locale: Locale): string {
  return (value?.[locale] ?? []).join("\n");
}

export function linesToList(text: string): string[] {
  return text
    .split("\n")
    .map((line) => line.trim())
    .filter(Boolean);
}

export function faqToLines(value: AdminFaqList | undefined, locale: Locale): string {
  return (value?.[locale] ?? []).map((item) => `${item.question} :: ${item.answer}`).join("\n");
}

export function linesToFaq(text: string): { question: string; answer: string }[] {
  return text
    .split("\n")
    .map((line) => line.trim())
    .filter(Boolean)
    .map((line) => {
      const [question, ...rest] = line.split("::");
      return { question: question.trim(), answer: rest.join("::").trim() };
    })
    .filter((item) => item.question && item.answer);
}

export function TranslatedFields({
  label,
  value,
  onChange,
  multiline,
}: {
  label: string;
  value: AdminTranslated;
  onChange: (next: AdminTranslated) => void;
  multiline?: boolean;
}) {
  return (
    <div className="space-y-1.5">
      <p className={labelClasses}>{label}</p>
      <div className="grid gap-2 sm:grid-cols-3">
        {LOCALES.map(({ key, label: localeLabel }) =>
          multiline ? (
            <textarea
              key={key}
              value={value[key] ?? ""}
              placeholder={localeLabel}
              rows={3}
              onChange={(e) => onChange({ ...value, [key]: e.target.value })}
              className={fieldClasses}
            />
          ) : (
            <input
              key={key}
              value={value[key] ?? ""}
              placeholder={localeLabel}
              onChange={(e) => onChange({ ...value, [key]: e.target.value })}
              className={fieldClasses}
            />
          ),
        )}
      </div>
    </div>
  );
}

/** One line per list item, per locale — used for included/excluded items, process steps, price factors, warnings. */
export function TranslatedListFields({
  label,
  hint,
  value,
  onChange,
}: {
  label: string;
  hint?: string;
  value: AdminTranslatedList;
  onChange: (next: AdminTranslatedList) => void;
}) {
  return (
    <div className="space-y-1.5">
      <p className={labelClasses}>
        {label} {hint ? <span className="font-normal text-ink-faint">({hint})</span> : null}
      </p>
      <div className="grid gap-2 sm:grid-cols-3">
        {LOCALES.map(({ key, label: localeLabel }) => (
          <textarea
            key={key}
            value={(value[key] ?? []).join("\n")}
            placeholder={localeLabel}
            rows={4}
            onChange={(e) => onChange({ ...value, [key]: linesToList(e.target.value) })}
            className={fieldClasses}
          />
        ))}
      </div>
    </div>
  );
}

/** "Question :: Answer" per line, per locale. */
export function FaqFields({
  value,
  onChange,
}: {
  value: AdminFaqList;
  onChange: (next: AdminFaqList) => void;
}) {
  return (
    <div className="space-y-1.5">
      <p className={labelClasses}>ՀՏՀ (Հարց :: Պատասխան, մեկը մեկ տողում)</p>
      <div className="grid gap-2 sm:grid-cols-3">
        {LOCALES.map(({ key, label: localeLabel }) => (
          <textarea
            key={key}
            value={faqToLines(value, key)}
            placeholder={localeLabel}
            rows={4}
            onChange={(e) => onChange({ ...value, [key]: linesToFaq(e.target.value) })}
            className={fieldClasses}
          />
        ))}
      </div>
    </div>
  );
}
