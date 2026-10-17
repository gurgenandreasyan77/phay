"use client";

import { useState } from "react";
import { FileSpreadsheet } from "lucide-react";
import type { AdminDataImportPreviewDto, DataTableSection } from "@/lib/adminApi";
import { Card } from "../ui";
import { IMPORT_ACCEPT, uploadImportFile } from "./ImportWizard";
import { TableDesigner } from "./TableDesigner";
import { tablePath } from "./shared";

/**
 * A new table, designed by hand or made from a file: the file's headers
 * become the columns (with guessed types), and once the table is saved the
 * same file goes straight to import.
 */
export function NewTableForm({ section }: { section: DataTableSection }) {
  const [preview, setPreview] = useState<AdminDataImportPreviewDto | null>(null);
  const [pending, setPending] = useState(false);
  const [error, setError] = useState<string | null>(null);

  async function upload(file: File) {
    setPending(true);
    setError(null);
    const result = await uploadImportFile(file);
    setPending(false);
    if ("error" in result) setError(result.error);
    else setPreview(result.preview);
  }

  return (
    <div className="space-y-6">
      <Card className="flex flex-wrap items-center gap-4">
        <FileSpreadsheet className="h-6 w-6 shrink-0 text-primary" />
        <div className="min-w-0 flex-1">
          {preview ? (
            <>
              <p className="font-medium text-ink">Սյունակները վերցված են «{preview.fileName}» ֆայլից</p>
              <p className="text-sm text-ink-muted">
                Ստուգեք տեսակները և ստեղծեք աղյուսակը․ հետո {preview.totalRows} տողերը կներմուծվեն նույն ֆայլից։
              </p>
            </>
          ) : (
            <>
              <p className="font-medium text-ink">Ունե՞ք արդեն Excel կամ CSV ֆայլ</p>
              <p className="text-sm text-ink-muted">
                Ընտրեք այն, և սյունակները կստեղծվեն ֆայլի վերնագրերից։ Կամ կազմեք աղյուսակը ձեռքով ստորև։
              </p>
            </>
          )}
          {error ? <p className="mt-1 text-sm text-danger">{error}</p> : null}
        </div>
        <label className="inline-flex h-10 cursor-pointer items-center rounded-md border border-border bg-surface px-4 text-sm font-medium text-ink hover:bg-surface-secondary">
          {pending ? "Կարդում ենք…" : preview ? "Այլ ֆայլ" : "Ընտրել ֆայլ"}
          <input
            type="file"
            accept={IMPORT_ACCEPT}
            className="sr-only"
            disabled={pending}
            onChange={(e) => {
              const file = e.target.files?.[0];
              if (file) upload(file);
              e.target.value = "";
            }}
          />
        </label>
      </Card>

      <TableDesigner
        key={preview?.id ?? "manual"}
        section={section}
        initial={
          preview
            ? { name: preview.fileName.replace(/\.[^.]+$/, ""), columns: preview.guessedColumns }
            : undefined
        }
        afterCreate={preview ? (saved) => `${tablePath(section, saved.id, "/import")}?import=${preview.id}` : undefined}
      />
    </div>
  );
}
