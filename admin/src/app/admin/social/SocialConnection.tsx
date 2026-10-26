"use client";

import { useRouter } from "next/navigation";
import { useEffect, useId, useState } from "react";
import { AlertTriangle, Check, Link2, RefreshCw, Settings2, Unplug } from "lucide-react";
import type { SocialStateDto } from "@/lib/adminApi";
import { Badge, Button, buttonClasses, Card, cn } from "@/ui";
import { ConfirmDialog } from "@/ui/dialog/ConfirmDialog";
import { Dialog, DialogClose } from "@/ui/dialog/Dialog";
import { formatDateTime, formatNumber } from "../_components/tables/shared";
import { NETWORKS, NOTICES, PERMISSIONS } from "./labels";

type Connection = NonNullable<SocialStateDto["connection"]>;

/**
 * Connecting the business's Facebook login (its Pages and the Instagram
 * accounts linked to them, read only), choosing which are the business's,
 * refreshing their figures, and disconnecting (everything read from Meta
 * is deleted).
 */
export function SocialConnection({
  state,
  canManage,
  notice,
}: {
  state: SocialStateDto;
  canManage: boolean;
  notice: string | null;
}) {
  const router = useRouter();
  const connection = state.connection;
  const [message, setMessage] = useState<{ text: string; tone: "success" | "danger" } | null>(
    notice && NOTICES[notice] ? NOTICES[notice] : null,
  );
  const [picking, setPicking] = useState(
    notice === "connected" && !!connection && connection.accounts.length > 0 && !connection.accounts.some((a) => a.selected),
  );
  const [asking, setAsking] = useState(false);
  const [pending, setPending] = useState(false);
  const [error, setError] = useState<string | null>(null);

  // The notice is shown once: a refresh doesn't bring it back.
  useEffect(() => {
    if (notice) router.replace("/admin/social", { scroll: false });
  }, [notice, router]);

  async function sync() {
    setPending(true);
    try {
      const response = await fetch("/api/admin/social/sync", { method: "POST" });
      const json = await response.json().catch(() => null);
      if (!response.ok) {
        setMessage({ text: failure(response.status, json), tone: "danger" });
        router.refresh();
        return;
      }
      const { result } = json.data;
      setMessage({
        text: `Թարմացված է․ ${result.accounts} հաշիվ, ${result.posts} գրառում, ${result.competitors.read} մրցակից։`,
        tone: "success",
      });
      router.refresh();
    } catch {
      setMessage({ text: "Չհաջողվեց։ Ստուգեք կապը և փորձեք կրկին։", tone: "danger" });
    } finally {
      setPending(false);
    }
  }

  async function disconnect() {
    setPending(true);
    setError(null);
    try {
      const response = await fetch("/api/admin/social", { method: "DELETE" });
      if (!response.ok) {
        setError("Չհաջողվեց անջատել։ Փորձեք կրկին։");
        return;
      }
      setAsking(false);
      setMessage({ text: "Instagram-ը և Facebook-ը անջատված են, Meta-ից ստացված տվյալները ջնջված են։", tone: "success" });
      router.refresh();
    } catch {
      setError("Չհաջողվեց անջատել։ Փորձեք կրկին։");
    } finally {
      setPending(false);
    }
  }

  const banner = message ? (
    <p
      role="status"
      className={cn(
        "rounded-md px-3 py-2 text-sm",
        message.tone === "success" ? "bg-success-light text-success" : "bg-danger-light text-danger",
      )}
    >
      {message.text}
    </p>
  ) : null;

  if (!state.configured) {
    return (
      <div className="space-y-3">
        {banner}
        <Card className="space-y-2">
          <p className="text-sm font-medium text-ink">Instagram-ի և Facebook-ի հետ կապը հարթակում դեռ կարգավորված չէ</p>
          <p className="text-sm text-ink-muted">Գրեք Phay-ի թիմին «Կապ Phay-ի հետ» բաժնում, և մենք կմիացնենք այն։</p>
        </Card>
      </div>
    );
  }

  if (!connection) {
    return (
      <div className="space-y-3">
        {banner}
        <Card className="space-y-4">
          <div className="space-y-2">
            <p className="text-base font-semibold text-ink">Միացրեք ձեր Instagram-ը և Facebook էջը</p>
            <p className="max-w-2xl text-sm text-ink-muted">
              Կտեսնեք հետևորդների աճը, ընդգրկումը, լավագույն գրառումները և ովքեր են ձեր հետևորդները, իսկ AI օգնականը
              կպատասխանի դրանց և մրցակիցների մասին հարցերին։ Մենք միայն կարդում ենք. ոչինչ չենք հրապարակում և
              հաղորդագրություններ չենք կարդում։
            </p>
          </div>
          <ul className="space-y-1.5 text-sm text-ink">
            <li>1. Instagram հաշիվը պետք է լինի պրոֆեսիոնալ՝ «Բիզնես» կամ «Ստեղծող» (Instagram → Կարգավորումներ → Հաշվի տեսակ)։</li>
            <li>2. Այն պետք է կապված լինի ձեր Facebook էջին (Instagram → Կարգավորումներ → Կենտրոն հաշիվներ կամ էջի կարգավորումներից)։</li>
            <li>3. Facebook-ի պատուհանում ընտրեք այդ էջը և Instagram հաշիվը, թողեք բոլոր թույլտվությունները։</li>
          </ul>
          {canManage ? (
            <a href="/api/admin/social/connect" className={buttonClasses("primary")}>
              <Link2 className="h-4 w-4" />
              Միացնել Facebook-ով
            </a>
          ) : (
            <p className="text-sm text-ink-muted">Միացնել կարող է բիզնեսի սեփականատերը կամ ադմինիստրատորը։</p>
          )}
        </Card>
      </div>
    );
  }

  const selected = connection.accounts.filter((a) => a.selected);

  return (
    <div className="space-y-3">
      {banner}
      <Warnings connection={connection} canManage={canManage} />

      <Card className="space-y-4">
        <div className="flex flex-wrap items-start justify-between gap-3">
          <div className="space-y-1">
            <p className="text-sm font-semibold text-ink">Facebook հաշիվ՝ {connection.name ?? "—"}</p>
            <p className="text-xs text-ink-muted">
              Թարմացվել է՝ {connection.syncedAt ? formatDateTime(connection.syncedAt) : "դեռ ոչ"} · թարմանում է ավտոմատ՝ օրը մի քանի
              անգամ
            </p>
            {connection.expiresAt ? (
              <p className="text-xs text-ink-muted">Թույլտվությունը գործում է մինչև {formatDateTime(connection.expiresAt).slice(0, 10)}</p>
            ) : null}
          </div>
          <div className="flex flex-wrap gap-2">
            <Button variant="secondary" size="sm" onClick={sync} disabled={pending || connection.needsReconnect || selected.length === 0}>
              <RefreshCw className={cn("h-4 w-4", pending && "animate-spin")} />
              Թարմացնել
            </Button>
            {canManage ? (
              <>
                <Button variant="secondary" size="sm" onClick={() => setPicking(true)} disabled={pending}>
                  <Settings2 className="h-4 w-4" />
                  Ընտրել էջերը
                </Button>
                <Button variant="ghost" size="sm" onClick={() => setAsking(true)} disabled={pending}>
                  <Unplug className="h-4 w-4" />
                  Անջատել
                </Button>
              </>
            ) : null}
          </div>
        </div>

        {selected.length === 0 ? (
          <p className="rounded-md bg-warning-light px-3 py-2 text-sm text-warning">
            Ընտրեք, թե որ էջն ու Instagram հաշիվն են ձեր բիզնեսինը՝ «Ընտրել էջերը»։
          </p>
        ) : (
          <ul className="flex flex-wrap gap-2">
            {selected.map((account) => (
              <li key={account.id}>
                <Badge>
                  <span style={{ color: NETWORKS[account.network]?.color }}>{NETWORKS[account.network]?.label}</span>
                  &nbsp;{account.handle}
                </Badge>
              </li>
            ))}
          </ul>
        )}
      </Card>

      {picking ? (
        <AccountPicker
          connection={connection}
          onClose={() => setPicking(false)}
          onSaved={(text, tone) => {
            setPicking(false);
            setMessage({ text, tone });
            router.refresh();
          }}
        />
      ) : null}

      <ConfirmDialog
        open={asking}
        pending={pending}
        error={error}
        onCancel={() => setAsking(false)}
        onConfirm={disconnect}
        content={{
          title: "Անջատե՞լ Instagram-ը և Facebook-ը",
          description:
            "Կջնջենք Meta-ից ստացված բոլոր տվյալները՝ վիճակագրությունը, գրառումները, հետևորդների տվյալները և մրցակիցների թվերը (մրցակիցների ցանկը կմնա)։ Կարող եք միացնել նորից ցանկացած պահի։",
          details: [{ label: "Facebook հաշիվ", value: connection.name ?? "—" }],
          confirmLabel: "Անջատել և ջնջել",
          tone: "danger",
        }}
      />
    </div>
  );
}

function Warnings({ connection, canManage }: { connection: Connection; canManage: boolean }) {
  const reconnect = canManage ? (
    <a href="/api/admin/social/connect" className={buttonClasses("primary", "sm")}>
      Միացնել նորից
    </a>
  ) : null;

  const warning = (text: string) => (
    <div className="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-warning/40 bg-warning-light px-4 py-3">
      <p className="flex items-start gap-2 text-sm text-warning">
        <AlertTriangle className="mt-0.5 h-4 w-4 shrink-0" />
        {text}
      </p>
      {reconnect}
    </div>
  );

  if (connection.needsReconnect) {
    return warning("Meta-ն այլևս չի թույլատրում կարդալ ձեր էջերը (թույլտվությունը ավարտվել է կամ հանվել է)։ Թվերը վերջին թարմացումից են։");
  }
  if (connection.missingPermissions.length > 0) {
    return warning(
      `Facebook-ում որոշ թույլտվություններ չեն տրվել (${connection.missingPermissions.map((p) => PERMISSIONS[p] ?? p).join(", ")}), ուստի որոշ թվեր չեն երևա։`,
    );
  }
  if (connection.expiringSoon) {
    return warning("Meta-ի թույլտվությունը շուտով ավարտվում է։ Միացրեք նորից, որպեսզի թվերը շարունակեն թարմանալ։");
  }
  if (connection.syncError === "rate_limited") {
    return warning("Meta-ն խնդրել է սպասել շատ հարցումների պատճառով։ Թվերը կթարմանան հաջորդ անգամ ինքնաշխատ։");
  }
  return null;
}

function AccountPicker({
  connection,
  onClose,
  onSaved,
}: {
  connection: Connection;
  onClose: () => void;
  onSaved: (message: string, tone: "success" | "danger") => void;
}) {
  const titleId = useId();
  const [chosen, setChosen] = useState(new Set(connection.accounts.filter((a) => a.selected).map((a) => a.id)));
  const [pending, setPending] = useState(false);
  const [failed, setFailed] = useState<string | null>(null);

  function toggle(id: number) {
    setChosen((current) => {
      const next = new Set(current);
      if (next.has(id)) next.delete(id);
      else next.add(id);
      return next;
    });
  }

  async function save() {
    setPending(true);
    setFailed(null);
    try {
      const response = await fetch("/api/admin/social/accounts", {
        method: "PUT",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ accounts: [...chosen] }),
      });
      const json = await response.json().catch(() => null);
      if (response.status === 429 || response.status === 409 || response.status === 502) {
        // Saved, but Meta wasn't read now: the next refresh reads it.
        onSaved(failure(response.status, json), "danger");
        return;
      }
      if (!response.ok) {
        setFailed("Չհաջողվեց պահպանել։ Փորձեք կրկին։");
        return;
      }
      onSaved(chosen.size > 0 ? "Պահպանված է, թվերը թարմացվեցին։" : "Պահպանված է։", "success");
    } catch {
      setFailed("Չհաջողվեց պահպանել։ Փորձեք կրկին։");
    } finally {
      setPending(false);
    }
  }

  return (
    <Dialog open onClose={() => (pending ? undefined : onClose())} labelledBy={titleId}>
      <div className="space-y-4">
        <div className="flex items-start justify-between gap-3">
          <div className="space-y-1">
            <h2 id={titleId} className="text-base font-semibold text-ink">
              Որ էջերն ու հաշիվներն են ձեր բիզնեսինը
            </h2>
            <p className="text-sm text-ink-muted">Ընտրվածների թվերը կերևան այստեղ և AI օգնականին։ Անձնական էջերը մի՛ ընտրեք։</p>
          </div>
          <DialogClose onClick={onClose} />
        </div>

        {connection.accounts.length === 0 ? (
          <p className="rounded-md bg-surface-secondary px-3 py-2 text-sm text-ink-muted">
            Այս Facebook հաշվով էջեր չգտնվեցին։ Միացնելիս Facebook-ի պատուհանում ընտրեք ձեր բիզնեսի էջը։
          </p>
        ) : (
          <ul className="divide-y divide-border rounded-lg border border-border">
            {connection.accounts.map((account) => (
              <li key={account.id}>
                <label
                  className={cn(
                    "flex cursor-pointer items-center gap-3 px-3 py-2.5 hover:bg-surface-secondary/60",
                    account.network === "instagram" && "pl-8",
                  )}
                >
                  <input type="checkbox" checked={chosen.has(account.id)} onChange={() => toggle(account.id)} />
                  <span className="text-xs font-semibold" style={{ color: NETWORKS[account.network]?.color }}>
                    {NETWORKS[account.network]?.label}
                  </span>
                  <span className="min-w-0 flex-1 break-words text-sm text-ink">{account.handle}</span>
                  {account.followers !== null ? (
                    <span className="whitespace-nowrap text-xs text-ink-faint">{formatNumber(account.followers)} հետևորդ</span>
                  ) : null}
                </label>
              </li>
            ))}
          </ul>
        )}

        <p className="text-xs text-ink-faint">
          Instagram հաշիվը երևում է իր Facebook էջի տակ։ Եթե ձեր Instagram-ը ցանկում չէ, այն դեռ պրոֆեսիոնալ չէ կամ կապված չէ էջին։
        </p>

        {failed ? <p className="text-sm text-danger">{failed}</p> : null}

        <div className="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
          <Button variant="secondary" onClick={onClose} disabled={pending}>
            Չեղարկել
          </Button>
          <Button onClick={save} disabled={pending}>
            <Check className="h-4 w-4" />
            {pending ? "Մի պահ…" : "Պահպանել"}
          </Button>
        </div>
      </div>
    </Dialog>
  );
}

export function failure(status: number, json: { code?: string; retryAfterMinutes?: number } | null): string {
  if (json?.code === "social_cooldown") return `Թվերը թարմացվել են մի քանի րոպե առաջ։ Փորձեք ${json.retryAfterMinutes ?? 10} րոպեից։`;
  if (json?.code === "meta_rate_limited") return "Meta-ն խնդրում է սպասել. շատ հարցումներ։ Արդեն ստացված թվերը պահված են։";
  if (json?.code === "meta_reconnect") return "Meta-ն այլևս չի թույլատրում կարդալ էջերը։ Միացրեք նորից։";
  if (status === 429) return "Շատ հաճախ եք սեղմում։ Փորձեք մեկ րոպե հետո։";
  if (status === 502) return "Meta-ն չպատասխանեց։ Փորձեք մի փոքր ուշ։";
  return "Չհաջողվեց։ Փորձեք կրկին։";
}
