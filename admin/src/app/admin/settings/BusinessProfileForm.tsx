"use client";

import { useState } from "react";
import { useRouter } from "next/navigation";
import { BusinessLogo } from "../_components/BusinessLogo";
import { Button, Card, FieldLabel, FormStatus, Input } from "../_components/ui";

/** The business's name and logo: shown in its admin and in the platform's list of businesses. */
export function BusinessProfileForm({ name: initialName, logo: initialLogo }: { name: string; logo: string | null }) {
  const router = useRouter();
  const [name, setName] = useState(initialName);
  const [logo, setLogo] = useState(initialLogo);
  const [status, setStatus] = useState<"idle" | "saving" | "saved" | "error">("idle");
  const [logoStatus, setLogoStatus] = useState<"idle" | "uploading" | "error" | "invalid">("idle");

  async function saveName(e: React.FormEvent) {
    e.preventDefault();
    if (!name.trim()) return;
    setStatus("saving");

    try {
      const response = await fetch("/api/admin/settings", {
        method: "PUT",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ name: name.trim() }),
      });
      setStatus(response.ok ? "saved" : "error");
      if (response.ok) router.refresh();
    } catch {
      setStatus("error");
    }
  }

  async function uploadLogo(file: File) {
    setLogoStatus("uploading");

    try {
      const formData = new FormData();
      formData.append("file", file);
      const response = await fetch("/api/admin/settings/logo", { method: "POST", body: formData });
      if (!response.ok) {
        setLogoStatus(response.status === 422 ? "invalid" : "error");
        return;
      }
      const json = (await response.json()) as { data: { business: { logo: string | null } } };
      setLogo(json.data.business.logo);
      setLogoStatus("idle");
      router.refresh();
    } catch {
      setLogoStatus("error");
    }
  }

  async function removeLogo() {
    if (!confirm("Հեռացնե՞լ լոգոն։")) return;
    setLogoStatus("uploading");

    try {
      const response = await fetch("/api/admin/settings/logo", { method: "DELETE" });
      if (!response.ok) {
        setLogoStatus("error");
        return;
      }
      setLogo(null);
      setLogoStatus("idle");
      router.refresh();
    } catch {
      setLogoStatus("error");
    }
  }

  return (
    <Card className="space-y-6">
      <div className="flex flex-wrap items-center gap-4">
        <BusinessLogo name={name || initialName} logo={logo} size="lg" />
        <div className="space-y-2">
          <div className="flex flex-wrap items-center gap-2">
            <label className="inline-flex h-8 cursor-pointer items-center rounded-md border border-border bg-surface px-3 text-xs font-medium text-ink hover:bg-surface-secondary">
              {logoStatus === "uploading" ? "Վերբեռնվում է…" : logo ? "Փոխել լոգոն" : "Վերբեռնել լոգո"}
              <input
                type="file"
                accept="image/jpeg,image/png,image/webp"
                className="hidden"
                disabled={logoStatus === "uploading"}
                onChange={(e) => {
                  const file = e.target.files?.[0];
                  if (file) uploadLogo(file);
                  e.target.value = "";
                }}
              />
            </label>
            {logo ? (
              <Button size="sm" variant="ghost" onClick={removeLogo} disabled={logoStatus === "uploading"}>
                Հեռացնել
              </Button>
            ) : null}
          </div>
          <p className="text-xs text-ink-muted">PNG, JPG կամ WEBP, մինչև 4 ՄԲ։ Լավագույնը քառակուսի պատկերն է։</p>
          {logoStatus === "invalid" ? (
            <p className="text-xs text-danger">Ֆայլը պետք է լինի PNG, JPG կամ WEBP պատկեր՝ մինչև 4 ՄԲ։</p>
          ) : null}
          {logoStatus === "error" ? <p className="text-xs text-danger">Չհաջողվեց պահպանել լոգոն։</p> : null}
        </div>
      </div>

      <form onSubmit={saveName} className="flex flex-wrap items-end gap-3">
        <div className="min-w-64 flex-1 space-y-1.5">
          <FieldLabel>Բիզնեսի անվանում</FieldLabel>
          <Input
            value={name}
            maxLength={255}
            required
            onChange={(e) => {
              setName(e.target.value);
              setStatus("idle");
            }}
          />
        </div>
        <Button type="submit" disabled={status === "saving" || !name.trim() || name.trim() === initialName}>
          {status === "saving" ? "Պահպանվում է…" : "Պահպանել"}
        </Button>
        <FormStatus status={status} />
      </form>
    </Card>
  );
}
