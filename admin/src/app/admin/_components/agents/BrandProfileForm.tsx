"use client";

import Link from "next/link";
import { useState } from "react";
import { useRouter } from "next/navigation";
import { Plus, Sparkles, X } from "lucide-react";
import type { AgentDto, BrandColorDto, BrandProfileDto } from "@/lib/adminApi";
import { Button, FieldLabel, Input, Textarea, cn } from "@/ui";
import { validationErrors } from "../tables/shared";
import { AgentAvatar } from "./AgentAvatar";
import styles from "./agents.module.css";

type TextField = "about" | "offer" | "audience" | "tone" | "visual_style" | "examples" | "avoid";

const FIELDS: {
  key: TextField;
  dto: keyof BrandProfileDto;
  label: string;
  hint: string;
  rows: number;
}[] = [
  {
    key: "about",
    dto: "about",
    label: "Ի՞նչ է ձեր բիզնեսը",
    hint: "Ինչ եք անում, որտեղ, որքան ժամանակ, ինչով եք տարբերվում մյուսներից",
    rows: 3,
  },
  {
    key: "offer",
    dto: "offer",
    label: "Ի՞նչ եք առաջարկում",
    hint: "Հիմնական ծառայությունները կամ ապրանքները, գները, ակցիաները",
    rows: 3,
  },
  {
    key: "audience",
    dto: "audience",
    label: "Ո՞վ են ձեր հաճախորդները",
    hint: "Տարիք, քաղաք, ինչու են գնում, ինչ է նրանց համար կարևոր",
    rows: 2,
  },
  {
    key: "tone",
    dto: "tone",
    label: "Ինչպե՞ս եք խոսում հաճախորդների հետ",
    hint: "Պաշտոնական կամ ընկերական, էմոջիներով թե առանց, որ լեզուներով",
    rows: 2,
  },
  {
    key: "visual_style",
    dto: "visualStyle",
    label: "Վիզուալ ոճ",
    hint: "Լույս, ֆոն, տրամադրություն, տառատեսակներ, ինչ պետք է լինի կադրում",
    rows: 2,
  },
  {
    key: "examples",
    dto: "examples",
    label: "Օրինակներ, որոնք ձեզ դուր են գալիս",
    hint: "Instagram էջեր, գրառումներ, գովազդներ, հղումներ",
    rows: 2,
  },
  {
    key: "avoid",
    dto: "avoid",
    label: "Ինչից խուսափել",
    hint: "Ինչ երբեք չպետք է երևա կամ ասվի",
    rows: 2,
  },
];

type Draft = Record<TextField, string> & { colors: BrandColorDto[] };

function draftOf(profile: BrandProfileDto): Draft {
  const draft = { colors: profile.colors.map((c) => ({ ...c })) } as Draft;
  for (const field of FIELDS) draft[field.key] = (profile[field.dto] as string | null) ?? "";
  return draft;
}

const HEX = /^#[0-9a-f]{6}$/i;

/**
 * The brand profile every agent of the team reads: what the business is,
 * for whom, how it speaks and looks (with its colours). The owner fills it
 * here, or tells an agent in the chat and the agent fills it.
 */
export function BrandProfileForm({ profile, teacher }: { profile: BrandProfileDto; teacher: AgentDto | null }) {
  const router = useRouter();
  const [draft, setDraft] = useState<Draft>(() => draftOf(profile));
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [status, setStatus] = useState<"idle" | "saving" | "saved" | "error">("idle");
  const percent = Math.round((profile.filled / Math.max(1, profile.total)) * 100);

  function setColor(index: number, patch: Partial<BrandColorDto>) {
    setDraft((d) => ({
      ...d,
      colors: d.colors.map((c, i) => (i === index ? { ...c, ...patch } : c)),
    }));
  }

  async function save() {
    setStatus("saving");
    const body: Record<string, unknown> = {
      colors: draft.colors.filter((c) => HEX.test(c.hex)).map((c) => ({ hex: c.hex, name: c.name?.trim() || null })),
    };
    for (const field of FIELDS) body[field.key] = draft[field.key].trim() || null;

    const response = await fetch("/api/admin/agents/profile", {
      method: "PUT",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(body),
    }).catch(() => null);

    if (response?.status === 422) {
      setErrors(validationErrors(await response.json().catch(() => null)));
      setStatus("idle");
      return;
    }
    if (!response?.ok) {
      setStatus("error");
      return;
    }
    setErrors({});
    setStatus("saved");
    router.refresh();
  }

  return (
    <div className="space-y-6">
      <div className="grid gap-4 rounded-lg border border-border bg-surface-secondary/50 p-4 sm:grid-cols-[1fr_auto] sm:items-center">
        <div className="space-y-2">
          <div className="flex items-center justify-between gap-3 text-sm">
            <span className="font-medium text-ink">
              {profile.complete ? "Թիմը ճանաչում է ձեր բիզնեսը" : "Թիմը դեռ սովորում է ձեր բիզնեսը"}
            </span>
            <span className="text-ink-muted">
              {profile.filled}/{profile.total}
            </span>
          </div>
          <div className="h-2 overflow-hidden rounded-full bg-surface">
            <div className={cn(styles.progress, "h-full rounded-full bg-primary")} style={{ width: `${percent}%` }} />
          </div>
        </div>
        {teacher ? (
          <Link
            href={`/admin/assistant?agent=${teacher.key}`}
            className="flex items-center gap-2 rounded-md border border-border bg-surface px-3 py-2 text-sm text-ink hover:border-primary"
          >
            <AgentAvatar agentKey={teacher.key} size={32} />
            <span>
              Կամ պատմեք {teacher.persona.hy}ին զրույցում
              <Sparkles className="ml-1 inline h-3.5 w-3.5 text-primary" aria-hidden="true" />
            </span>
          </Link>
        ) : null}
      </div>

      <form
        className="grid gap-4 lg:grid-cols-2"
        onSubmit={(e) => {
          e.preventDefault();
          save();
        }}
      >
        {FIELDS.map((field) => (
          <label
            key={field.key}
            className={cn("space-y-1.5", (field.key === "about" || field.key === "offer") && "lg:col-span-2")}
          >
            <FieldLabel>{field.label}</FieldLabel>
            <Textarea
              rows={field.rows}
              maxLength={1500}
              value={draft[field.key]}
              placeholder={field.hint}
              onChange={(e) => setDraft((d) => ({ ...d, [field.key]: e.target.value }))}
            />
            {errors[field.key] ? <p className="text-xs text-danger">{errors[field.key]}</p> : null}
          </label>
        ))}

        <div className="space-y-2 lg:col-span-2">
          <FieldLabel>Բրենդի գույները</FieldLabel>
          <div className="space-y-2">
            {draft.colors.map((color, index) => (
              <div key={index} className="flex items-center gap-2">
                <input
                  type="color"
                  value={HEX.test(color.hex) ? color.hex : "#3f6f62"}
                  onChange={(e) => setColor(index, { hex: e.target.value.toUpperCase() })}
                  className="h-10 w-12 cursor-pointer rounded-md border border-border bg-surface p-1"
                  aria-label="Գույն"
                />
                <Input
                  className="w-28! font-mono uppercase"
                  value={color.hex}
                  maxLength={7}
                  onChange={(e) =>
                    setColor(index, {
                      hex: e.target.value.startsWith("#") ? e.target.value : `#${e.target.value}`,
                    })
                  }
                  aria-label="HEX"
                />
                <Input
                  className="w-auto! min-w-0 flex-1"
                  value={color.name ?? ""}
                  maxLength={40}
                  placeholder="Անուն, օր.՝ մուգ կապույտ"
                  onChange={(e) => setColor(index, { name: e.target.value })}
                />
                <Button
                  type="button"
                  size="sm"
                  variant="ghost"
                  aria-label="Հեռացնել գույնը"
                  onClick={() =>
                    setDraft((d) => ({
                      ...d,
                      colors: d.colors.filter((_, i) => i !== index),
                    }))
                  }
                >
                  <X className="h-4 w-4" />
                </Button>
              </div>
            ))}
          </div>
          {draft.colors.length < 8 ? (
            <Button
              type="button"
              size="sm"
              variant="secondary"
              onClick={() =>
                setDraft((d) => ({
                  ...d,
                  colors: [...d.colors, { hex: "#3F6F62", name: "" }],
                }))
              }
            >
              <Plus className="h-4 w-4" />
              Ավելացնել գույն
            </Button>
          ) : null}
          {draft.colors.some((c) => HEX.test(c.hex)) ? (
            <div className="flex h-8 overflow-hidden rounded-md border border-border" aria-hidden="true">
              {draft.colors
                .filter((c) => HEX.test(c.hex))
                .map((c, i) => (
                  <span key={i} className="flex-1" style={{ background: c.hex }} />
                ))}
            </div>
          ) : null}
          {Object.keys(errors).some((k) => k.startsWith("colors")) ? (
            <p className="text-xs text-danger">Ստուգեք գույների կոդերը։</p>
          ) : null}
        </div>

        <div className="flex items-center gap-3 lg:col-span-2">
          <Button type="submit" disabled={status === "saving"}>
            {status === "saving" ? "Պահպանվում է…" : "Պահպանել"}
          </Button>
          {status === "saved" ? <p className="text-sm text-success">Պահպանված է։ Թիմը կօգտագործի սա հաջորդ հարցից։</p> : null}
          {status === "error" ? <p className="text-sm text-danger">Չհաջողվեց պահպանել։ Փորձեք կրկին։</p> : null}
        </div>
      </form>
    </div>
  );
}
