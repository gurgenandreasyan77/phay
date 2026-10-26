"use client";

import { usePathname, useRouter } from "next/navigation";
import { useEffect, useMemo, useRef, useState } from "react";
import { Loader2, Search, X } from "lucide-react";
import type { FeatureFlags } from "@/lib/api";
import type { AdminUserDto } from "@/lib/adminApi";
import { visibleNavGroups } from "../nav";
import { cn } from "@/ui";

type SearchItem = { id: number | string; title: string; subtitle: string | null; url: string };
type SearchGroup = { key: string; items: SearchItem[] };

const GROUP_LABELS: Record<string, string> = {
  pages: "Բաժիններ",
  tables: "Աղյուսակներ",
  rows: "Գրառումներ աղյուսակներում",
  leads: "Պատվերներ կայքից",
  employees: "Աշխատակիցներ",
  services: "Ծառայություններ",
  businesses: "Բիզնեսներ",
};

const MIN_LENGTH = 2;

/**
 * The header's search: the admin's own sections (matched here) and what the
 * business has (GET /admin/search, by role and module). Ctrl/⌘+K opens it.
 */
export function HeaderSearch({ features, me }: { features: FeatureFlags; me: AdminUserDto | null }) {
  const router = useRouter();
  const pathname = usePathname();
  const [open, setOpen] = useState(false);
  const [query, setQuery] = useState("");
  const [remote, setRemote] = useState<{ query: string; groups: SearchGroup[] } | null>(null);
  const [loading, setLoading] = useState(false);
  const [active, setActive] = useState(0);
  const inputRef = useRef<HTMLInputElement>(null);

  const trimmed = query.trim();

  useEffect(() => {
    function handleKey(e: KeyboardEvent) {
      if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === "k") {
        e.preventDefault();
        setOpen(true);
      }
    }
    document.addEventListener("keydown", handleKey);
    return () => document.removeEventListener("keydown", handleKey);
  }, []);

  useEffect(() => {
    if (open) setTimeout(() => inputRef.current?.focus(), 0);
  }, [open]);

  useEffect(() => {
    if (trimmed.length < MIN_LENGTH) return;
    const controller = new AbortController();
    const timer = setTimeout(async () => {
      setLoading(true);
      try {
        const response = await fetch(`/api/admin/search?q=${encodeURIComponent(trimmed)}`, {
          signal: controller.signal,
          cache: "no-store",
        });
        const json = response.ok ? ((await response.json()) as { data: { groups: SearchGroup[] } | null }) : null;
        setRemote({ query: trimmed, groups: json?.data?.groups ?? [] });
      } catch {
        // Aborted by the next keystroke, or offline: keep what is shown.
      } finally {
        if (!controller.signal.aborted) setLoading(false);
      }
    }, 250);
    return () => {
      clearTimeout(timer);
      controller.abort();
    };
  }, [trimmed]);

  const pages = useMemo<SearchGroup | null>(() => {
    if (trimmed.length < MIN_LENGTH) return null;
    const needle = trimmed.toLocaleLowerCase("hy");
    const items = visibleNavGroups(features, me, pathname)
      .flatMap((group) => group.items.map((item) => ({ item, group: group.label })))
      .filter(({ item }) => !item.soon && item.label.toLocaleLowerCase("hy").includes(needle))
      .map(({ item, group }) => ({ id: item.href, title: item.label, subtitle: group, url: item.href }));
    return items.length > 0 ? { key: "pages", items } : null;
  }, [trimmed, features, me, pathname]);

  const groups = useMemo(() => {
    if (trimmed.length < MIN_LENGTH) return [];
    const fromApi = remote && remote.query === trimmed ? remote.groups : [];
    return [...(pages ? [pages] : []), ...fromApi];
  }, [trimmed, pages, remote]);

  const flat = groups.flatMap((group) => group.items);
  const waiting = trimmed.length >= MIN_LENGTH && (loading || remote?.query !== trimmed);

  function close() {
    setOpen(false);
    setQuery("");
    setRemote(null);
    setActive(0);
  }

  function go(item: SearchItem) {
    close();
    router.push(item.url);
  }

  function handleKeyDown(e: React.KeyboardEvent<HTMLInputElement>) {
    if (e.key === "Escape") {
      close();
    } else if (e.key === "ArrowDown") {
      e.preventDefault();
      setActive((i) => Math.min(i + 1, flat.length - 1));
    } else if (e.key === "ArrowUp") {
      e.preventDefault();
      setActive((i) => Math.max(i - 1, 0));
    } else if (e.key === "Enter" && flat[active]) {
      e.preventDefault();
      go(flat[active]);
    }
  }

  let index = -1;

  return (
    <>
      <button
        type="button"
        onClick={() => setOpen(true)}
        aria-label="Որոնում"
        title="Որոնում (Ctrl+K)"
        className="flex h-11 w-11 items-center justify-center rounded-md text-ink-muted hover:bg-surface-secondary hover:text-ink active:bg-surface-secondary sm:h-9 sm:w-9"
      >
        <Search className="h-4.5 w-4.5" strokeWidth={1.75} />
      </button>

      {open ? (
        <div className="fixed inset-0 z-50 flex items-start justify-center px-3 pt-[max(0.75rem,env(safe-area-inset-top))] sm:pt-[10vh]" role="dialog" aria-modal="true" aria-label="Որոնում">
          <button type="button" aria-label="Փակել" className="absolute inset-0 bg-ink/30" onClick={close} />
          <div className="relative w-full max-w-xl overflow-hidden rounded-xl border border-border bg-surface shadow-lg">
            <div className="flex items-center gap-2 border-b border-border px-4">
              <Search className="h-4 w-4 shrink-0 text-ink-muted" strokeWidth={1.75} />
              <input
                ref={inputRef}
                value={query}
                onChange={(e) => {
                  setQuery(e.target.value);
                  setActive(0);
                }}
                onKeyDown={handleKeyDown}
                enterKeyHint="search"
                placeholder="Որոնել պատվերներ, հաճախորդներ, աշխատակիցներ, բաժիններ…"
                className="h-12 w-full bg-transparent text-sm text-ink outline-none placeholder:text-ink-faint"
                style={{ outline: "none" }}
              />
              {waiting ? <Loader2 className="h-4 w-4 shrink-0 animate-spin text-ink-faint" strokeWidth={1.75} /> : null}
              <button
                type="button"
                onClick={close}
                aria-label="Փակել"
                className="-mr-2 flex h-10 w-10 shrink-0 items-center justify-center rounded-md text-ink-muted hover:bg-surface-secondary sm:mr-0 sm:h-7 sm:w-7"
              >
                <X className="h-4 w-4" strokeWidth={1.75} />
              </button>
            </div>

            <div className="overscroll-contain max-h-[60dvh] overflow-y-auto py-2">
              {trimmed.length < MIN_LENGTH ? (
                <p className="px-4 py-6 text-center text-sm text-ink-muted">Գրեք առնվազն {MIN_LENGTH} տառ։</p>
              ) : groups.length === 0 ? (
                <p className="px-4 py-6 text-center text-sm text-ink-muted">{waiting ? "Որոնում…" : "Ոչինչ չի գտնվել։"}</p>
              ) : (
                groups.map((group) => (
                  <div key={group.key} className="pb-1">
                    <p className="px-4 pb-1 pt-2 text-[11px] font-medium uppercase tracking-wide text-ink-faint">
                      {GROUP_LABELS[group.key] ?? group.key}
                    </p>
                    <ul>
                      {group.items.map((item) => {
                        index += 1;
                        const mine = index;
                        return (
                          <li key={`${group.key}-${item.id}`}>
                            <button
                              type="button"
                              onClick={() => go(item)}
                              onMouseEnter={() => setActive(mine)}
                              className={cn(
                                "flex w-full flex-col px-4 py-2 text-left",
                                active === mine ? "bg-surface-secondary" : "hover:bg-surface-secondary",
                              )}
                            >
                              <span className="truncate text-sm text-ink">{item.title}</span>
                              {item.subtitle ? <span className="truncate text-xs text-ink-muted">{item.subtitle}</span> : null}
                            </button>
                          </li>
                        );
                      })}
                    </ul>
                  </div>
                ))
              )}
            </div>
          </div>
        </div>
      ) : null}
    </>
  );
}
