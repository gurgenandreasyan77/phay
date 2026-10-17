"use client";

import { useState } from "react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { Upload } from "lucide-react";
import type { AdminDataImportDto, AdminDataImportPreviewDto, AdminDataTableDto, DataTableSection } from "@/lib/adminApi";
import { Badge, Button, buttonClasses, Card, EmptyRow, FieldLabel, Input, Select, TableWrap, Td, Th, Thead, Tr } from "../ui";
import { COLUMN_TYPE_LABELS, formatDateTime, roleLabels, tablePath, validationErrors } from "./shared";

export const IMPORT_ACCEPT = ".csv,.tsv,.txt,.xlsx,.xml";

/** Sends a file to the import endpoint; resolves to the preview or an error to show. */
export async function uploadImportFile(
  file: File,
  target: { tableId: number } | { section: DataTableSection },
): Promise<{ preview: AdminDataImportPreviewDto } | { error: string }> {
  const form = new FormData();
  form.append("file", file);
  // For a new table, the API also finds the section's tables the file fits.
  if ("tableId" in target) form.append("data_table_id", String(target.tableId));
  else form.append("section", target.section);

  try {
    const response = await fetch("/api/admin/data-imports", { method: "POST", body: form });
    const json = await response.json().catch(() => null);
    if (response.status === 422) return { error: validationErrors(json).file ?? "Ֆայլը չհաջողվեց կարդալ։" };
    if (response.status === 413) return { error: "Ֆայլը չափազանց մեծ է (առավելագույնը 10 ՄԲ)։" };
    if (!response.ok) return { error: "Չհաջողվեց բեռնել ֆայլը։" };
    return { preview: (json as { data: AdminDataImportPreviewDto }).data };
  } catch {
    return { error: "Չհաջողվեց բեռնել ֆայլը։" };
  }
}

const STATUS: Record<AdminDataImportDto["status"], { label: string; tone: "success" | "default" | "warning" }> = {
  done: { label: "Ներմուծված", tone: "success" },
  undone: { label: "Հետ վերցված", tone: "default" },
  uploaded: { label: "Չավարտված", tone: "warning" },
};

/**
 * Upload a file → check which file column goes into which table column
 * (matched by name, changeable) with a preview → import → report of what
 * was skipped and why. An import can be taken back.
 */
export function ImportWizard({
  table,
  initialPreview,
  history,
}: {
  table: AdminDataTableDto;
  initialPreview: AdminDataImportPreviewDto | null;
  history: AdminDataImportDto[];
}) {
  const router = useRouter();
  const [preview, setPreview] = useState<AdminDataImportPreviewDto | null>(initialPreview);
  const [mapping, setMapping] = useState<Record<string, number | null>>(initialPreview?.mapping ?? {});
  const [report, setReport] = useState<AdminDataImportDto | null>(null);
  const [pending, setPending] = useState(false);
  const [error, setError] = useState<string | null>(null);
  // One date for the rows when the file has no date column (a day's file).
  const [defaultDate, setDefaultDate] = useState(() => new Date().toLocaleDateString("sv-SE"));

  async function upload(file: File) {
    setPending(true);
    setError(null);
    const result = await uploadImportFile(file, { tableId: table.id });
    setPending(false);

    if ("error" in result) {
      setError(result.error);
      return;
    }
    setPreview(result.preview);
    setMapping(result.preview.mapping ?? {});
  }

  async function run() {
    if (!preview) return;
    setPending(true);
    setError(null);
    try {
      const response = await fetch(`/api/admin/data-imports/${preview.id}/run`, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ data_table_id: table.id, mapping, default_date: dateMissing ? defaultDate : null }),
      });
      const json = await response.json().catch(() => null);
      if (!response.ok) {
        setError(
          validationErrors(json).mapping ??
            validationErrors(json).default_date ??
            (json as { message?: string } | null)?.message ??
            "Չհաջողվեց ներմուծել։",
        );
        return;
      }
      setReport((json as { data: AdminDataImportDto }).data);
      setPreview(null);
      router.refresh();
    } catch {
      setError("Չհաջողվեց ներմուծել։");
    } finally {
      setPending(false);
    }
  }

  async function undo(item: AdminDataImportDto) {
    if (!confirm(`Ջնջե՞լ «${item.fileName}» ֆայլից ներմուծված ${item.importedRows} տողերը։`)) return;
    const response = await fetch(`/api/admin/data-imports/${item.id}/undo`, { method: "POST" });
    if (response.ok) {
      if (report?.id === item.id) setReport((await response.json()).data);
      router.refresh();
    }
  }

  const mappedCount = Object.values(mapping).filter((index) => index !== null && index !== undefined).length;
  const dateKey = table.dateColumn;
  const dateLabel = table.columns.find((c) => c.key === dateKey)?.label;
  const dateMissing = dateKey !== null && (mapping[dateKey] ?? null) === null;
  const usedHeaders = new Set(Object.values(mapping));
  const unusedHeaders = preview ? preview.headers.filter((_, index) => !usedHeaders.has(index)) : [];

  return (
    <div className="space-y-8">
      {report ? (
        <ImportReport report={report} table={table} onUndo={() => undo(report)} onAgain={() => setReport(null)} />
      ) : preview ? (
        <div className="space-y-4">
          <Card className="flex flex-wrap items-center gap-x-6 gap-y-2">
            <p className="font-medium text-ink">{preview.fileName}</p>
            <p className="text-sm text-ink-muted">
              {preview.totalRows} տող · {preview.headers.length} սյունակ
            </p>
            <Button variant="ghost" size="sm" className="ml-auto" onClick={() => setPreview(null)}>
              Ընտրել այլ ֆայլ
            </Button>
          </Card>
          {preview.truncated ? (
            <p className="text-sm text-warning">
              Ֆայլում {preview.maxRows}-ից ավելի տող կա․ կներմուծվեն առաջին {preview.maxRows}-ը։ Մնացածը բաժանեք առանձին
              ֆայլի։
            </p>
          ) : null}

          <div>
            <h2 className="text-base font-semibold text-ink">Ո՞ր սյունակը որտեղ</h2>
            <p className="mt-1 max-w-2xl text-sm text-ink-muted">
              Համընկնող անունները ընտրված են ինքնաշխատ։ Ստուգեք օրինակները և անհրաժեշտության դեպքում փոխեք։
            </p>
          </div>

          <TableWrap>
            <Thead>
              <Th>Աղյուսակի սյունակ</Th>
              <Th>Ֆայլի սյունակ</Th>
              <Th>Օրինակներ ֆայլից</Th>
            </Thead>
            <tbody>
              {table.columns.map((column) => {
                const index = mapping[column.key] ?? null;
                return (
                  <Tr key={column.key}>
                    <Td className="whitespace-nowrap">
                      <span className="font-medium">{column.label}</span>
                      {column.required ? <span className="text-danger"> *</span> : null}
                      <span className="block text-xs text-ink-faint">
                        {column.role ? `${roleLabels(column.role, table.section).hy} · ` : ""}
                        {COLUMN_TYPE_LABELS[column.type]}
                      </span>
                    </Td>
                    <Td className="w-72">
                      <Select
                        value={index === null ? "" : String(index)}
                        onChange={(e) =>
                          setMapping((m) => ({ ...m, [column.key]: e.target.value === "" ? null : Number(e.target.value) }))
                        }
                      >
                        <option value="">— Չներմուծել</option>
                        {preview.headers.map((header, i) => (
                          <option key={i} value={i}>
                            {header}
                          </option>
                        ))}
                      </Select>
                    </Td>
                    <Td className="text-ink-muted">
                      {index === null
                        ? "—"
                        : preview.sampleRows
                            .slice(0, 3)
                            .map((row) => row[index] || "∅")
                            .join(" · ")}
                    </Td>
                  </Tr>
                );
              })}
            </tbody>
          </TableWrap>

          {unusedHeaders.length > 0 ? (
            <p className="text-sm text-ink-muted">
              Չեն ներմուծվի ֆայլի սյունակները՝ {unusedHeaders.join(", ")}։ Եթե դրանք պետք են, նախ ավելացրեք համապատասխան
              սյունակ աղյուսակում։
            </p>
          ) : null}

          {dateMissing ? (
            <Card className="space-y-2 border-warning/30 bg-warning-light">
              <p className="text-sm text-warning">
                Ֆայլում «{dateLabel}» սյունակը ընտրված չէ։ Առանց ամսաթվի տողերը չեն երևա վերլուծությունում, ուստի նշեք՝ որ
                օրվա տվյալներն են։
              </p>
              <label className="block max-w-56 space-y-1.5">
                <FieldLabel>Բոլոր տողերի ամսաթիվը</FieldLabel>
                <Input type="date" value={defaultDate} onChange={(e) => setDefaultDate(e.target.value)} />
              </label>
            </Card>
          ) : null}

          <div className="flex flex-wrap items-center gap-3">
            <Button onClick={run} disabled={pending || mappedCount === 0 || (dateMissing && !defaultDate)}>
              {pending ? "Ներմուծվում է…" : `Ներմուծել ${preview.totalRows} տող`}
            </Button>
            {error ? <span className="text-sm text-danger">{error}</span> : null}
          </div>
        </div>
      ) : (
        <Card className="space-y-3">
          <label className="flex cursor-pointer flex-col items-center justify-center gap-2 rounded-lg border border-dashed border-border-strong px-4 py-10 text-center hover:bg-surface-secondary">
            <Upload className="h-6 w-6 text-ink-muted" />
            <span className="text-sm font-medium text-ink">{pending ? "Կարդում ենք ֆայլը…" : "Ընտրեք ֆայլը"}</span>
            <span className="text-xs text-ink-muted">CSV, XLSX (Excel, Google Sheets) կամ XML, մինչև 10 ՄԲ</span>
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
          <p className="text-xs text-ink-muted">
            Ֆայլի առաջին տողը պետք է լինի սյունակների անունները։ Excel-ում բացված է առաջին թերթը։
          </p>
          {error ? <p className="text-sm text-danger">{error}</p> : null}
        </Card>
      )}

      <div className="space-y-3">
        <h2 className="text-base font-semibold text-ink">Ներմուծումների պատմություն</h2>
        <TableWrap>
          <Thead>
            <Th>Ամսաթիվ</Th>
            <Th>Ֆայլ</Th>
            <Th>Ով</Th>
            <Th>Ավելացված</Th>
            <Th>Բաց թողնված</Th>
            <Th>Կարգավիճակ</Th>
            <Th />
          </Thead>
          <tbody>
            {history.map((item) => (
              <Tr key={item.id}>
                <Td className="whitespace-nowrap text-ink-muted">
                  {formatDateTime(item.createdAt)}
                </Td>
                <Td>{item.fileName}</Td>
                <Td className="text-ink-muted">{item.user ?? "—"}</Td>
                <Td>{item.status === "uploaded" ? "—" : item.importedRows}</Td>
                <Td>{item.status === "uploaded" ? "—" : item.skippedRows}</Td>
                <Td>
                  <Badge tone={STATUS[item.status].tone}>{STATUS[item.status].label}</Badge>
                </Td>
                <Td className="text-right">
                  {item.status === "done" && item.importedRows > 0 ? (
                    <Button variant="secondary" size="sm" onClick={() => undo(item)}>
                      Հետ վերցնել
                    </Button>
                  ) : null}
                </Td>
              </Tr>
            ))}
            {history.length === 0 ? <EmptyRow colSpan={7}>Այս աղյուսակում դեռ ներմուծում չի եղել։</EmptyRow> : null}
          </tbody>
        </TableWrap>
      </div>
    </div>
  );
}

function ImportReport({
  report,
  table,
  onUndo,
  onAgain,
}: {
  report: AdminDataImportDto;
  table: AdminDataTableDto;
  onUndo: () => void;
  onAgain: () => void;
}) {
  return (
    <div className="space-y-4">
      <div className="grid gap-4 sm:grid-cols-3">
        <Card className="border-success-light bg-success-light">
          <p className="text-sm text-success">Ավելացվեց</p>
          <p className="mt-1 text-3xl font-semibold text-success">{report.status === "undone" ? 0 : report.importedRows}</p>
        </Card>
        <Card>
          <p className="text-sm text-ink-muted">Բաց թողնվեց</p>
          <p className="mt-1 text-3xl font-semibold text-ink">{report.skippedRows}</p>
        </Card>
        <Card>
          <p className="text-sm text-ink-muted">Ֆայլ</p>
          <p className="mt-1 truncate font-medium text-ink">{report.fileName}</p>
          {report.status === "undone" ? <p className="mt-1 text-xs text-ink-muted">Ներմուծումը հետ է վերցված։</p> : null}
        </Card>
      </div>

      {report.errors.length > 0 ? (
        <div className="space-y-2">
          <h2 className="text-base font-semibold text-ink">Բաց թողնված տողեր</h2>
          <p className="text-sm text-ink-muted">Ուղղեք դրանք ֆայլում և ներմուծեք միայն այդ տողերը, կամ ավելացրեք ձեռքով։</p>
          <TableWrap>
            <Thead>
              <Th className="w-28">Տող ֆայլում</Th>
              <Th>Պատճառ</Th>
            </Thead>
            <tbody>
              {report.errors.map((row) => (
                <Tr key={row.line}>
                  <Td>{row.line}</Td>
                  <Td className="text-ink-muted">
                    {Object.entries(row.errors).map(([label, message]) => (
                      <span key={label} className="mr-3 inline-block">
                        <span className="font-medium text-ink">{label}</span>՝ {message}
                      </span>
                    ))}
                  </Td>
                </Tr>
              ))}
            </tbody>
          </TableWrap>
          {report.skippedRows > report.errors.length ? (
            <p className="text-xs text-ink-muted">Ցույց են տրված առաջին {report.errors.length}-ը։</p>
          ) : null}
        </div>
      ) : null}

      <div className="flex flex-wrap gap-3">
        <Link href={tablePath(table.section, table.id)} className={buttonClasses("primary")}>
          Բացել աղյուսակը
        </Link>
        <Link href="/admin/analytics" className={buttonClasses("secondary")}>
          Տեսնել վերլուծությունը
        </Link>
        <Button variant="secondary" onClick={onAgain}>
          Ներմուծել ևս մեկ ֆայլ
        </Button>
        {report.status === "done" && report.importedRows > 0 ? (
          <Button variant="ghost" onClick={onUndo}>
            Հետ վերցնել ներմուծումը
          </Button>
        ) : null}
      </div>
    </div>
  );
}
