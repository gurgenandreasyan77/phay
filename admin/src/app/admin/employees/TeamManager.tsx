"use client";

import { useState } from "react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { BriefcaseBusiness, CalendarPlus, Mail, Phone, Trash2, UserPlus } from "lucide-react";
import type {
  AbsenceDto,
  AbsenceKind,
  AdminEmployeeDto,
  CandidateDto,
  CandidateStage,
  JobOpeningDto,
  TeamAbsencesDto,
  TeamHiringDto,
} from "@/lib/adminApi";
import {
  Badge,
  type BadgeTone,
  Button,
  Card,
  EmptyCard,
  EmptyRow,
  FieldLabel,
  Input,
  Select,
  SectionCard,
  TableWrap,
  Td,
  Textarea,
  Th,
  Thead,
  Tr,
} from "@/ui";
import { validationErrors } from "../_components/tables/shared";

async function send(url: string, method: string, body?: unknown): Promise<{ ok: boolean; errors: Record<string, string> }> {
  const response = await fetch(url, {
    method,
    headers: { "Content-Type": "application/json" },
    body: body === undefined ? undefined : JSON.stringify(body),
  }).catch(() => null);
  if (!response) return { ok: false, errors: {} };
  if (response.status === 422) return { ok: false, errors: validationErrors(await response.json().catch(() => null)) };
  return { ok: response.ok, errors: {} };
}

function FieldError({ text }: { text?: string }) {
  return text ? <p className="mt-1 text-xs text-danger">{text}</p> : null;
}

const KINDS: { value: AbsenceKind; label: string; tone: BadgeTone }[] = [
  { value: "vacation", label: "Արձակուրդ", tone: "success" },
  { value: "day_off", label: "Հանգստյան օր", tone: "info" },
  { value: "sick", label: "Հիվանդություն", tone: "warning" },
  { value: "unpaid", label: "Առանց վարձատրության", tone: "default" },
  { value: "other", label: "Այլ", tone: "default" },
];

const MONTHS = ["հնվ", "փտվ", "մրտ", "ապր", "մյս", "հնս", "հլս", "օգս", "սեպ", "հոկ", "նոյ", "դեկ"];

/** "27 հոկ": the same on the server and in the browser (no locale data needed). */
const shortDate = (date: string) => `${Number(date.slice(8, 10))} ${MONTHS[Number(date.slice(5, 7)) - 1]}`;

function range(a: AbsenceDto) {
  return a.from === a.to ? shortDate(a.from) : `${shortDate(a.from)} – ${shortDate(a.to)}`;
}

/**
 * Time off: vacations, days off, sick days of the employees. Who is away
 * today, what is planned, and how many vacation days each one took this
 * year. The HR agent Անյա adds the same from the chat, as a card.
 */
export function AbsencesManager({ data, employees }: { data: TeamAbsencesDto; employees: AdminEmployeeDto[] }) {
  const router = useRouter();
  const [draft, setDraft] = useState({
    employeeId: "",
    kind: "vacation" as AbsenceKind,
    from: data.today,
    to: data.today,
    note: "",
  });
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [pending, setPending] = useState(false);

  const active = employees.filter((e) => e.isActive);
  const now = data.absences.filter((a) => a.from <= data.today && a.to >= data.today);
  const ahead = data.absences.filter((a) => a.from > data.today);
  const past = data.absences.filter((a) => a.to < data.today).reverse();

  async function add() {
    setPending(true);
    const result = await send("/api/admin/team/absences", "POST", {
      employeeId: draft.employeeId ? Number(draft.employeeId) : null,
      kind: draft.kind,
      from: draft.from,
      to: draft.to,
      note: draft.note || null,
    });
    setPending(false);
    setErrors(result.errors);
    if (!result.ok) return;
    setDraft((d) => ({ ...d, employeeId: "", note: "" }));
    router.refresh();
  }

  async function remove(absence: AbsenceDto) {
    if (!confirm(`Ջնջե՞լ ${absence.employee?.name ?? ""}-ի ${absence.kindLabel.toLowerCase()}ը (${range(absence)})։`)) return;
    if ((await send(`/api/admin/team/absences/${absence.id}`, "DELETE")).ok) router.refresh();
  }

  return (
    <>
      <Card>
        <form
          className="space-y-4"
          onSubmit={(e) => {
            e.preventDefault();
            add();
          }}
        >
          <h2 className="text-base font-semibold text-ink">Նշել բացակայություն</h2>
          <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <label className="space-y-1.5">
              <FieldLabel>Աշխատակից *</FieldLabel>
              <Select value={draft.employeeId} onChange={(e) => setDraft((d) => ({ ...d, employeeId: e.target.value }))}>
                <option value="">Ընտրեք…</option>
                {active.map((e) => (
                  <option key={e.id} value={e.id}>
                    {e.position ? `${e.name} · ${e.position}` : e.name}
                  </option>
                ))}
              </Select>
              <FieldError text={errors.employeeId} />
            </label>
            <label className="space-y-1.5">
              <FieldLabel>Տեսակ</FieldLabel>
              <Select value={draft.kind} onChange={(e) => setDraft((d) => ({ ...d, kind: e.target.value as AbsenceKind }))}>
                {KINDS.map((k) => (
                  <option key={k.value} value={k.value}>
                    {k.label}
                  </option>
                ))}
              </Select>
            </label>
            <label className="space-y-1.5">
              <FieldLabel>Սկսած</FieldLabel>
              <Input
                type="date"
                value={draft.from}
                onChange={(e) =>
                  setDraft((d) => ({ ...d, from: e.target.value, to: d.to < e.target.value ? e.target.value : d.to }))
                }
              />
              <FieldError text={errors.from} />
            </label>
            <label className="space-y-1.5">
              <FieldLabel>Մինչև (ներառյալ)</FieldLabel>
              <Input
                type="date"
                value={draft.to}
                min={draft.from}
                onChange={(e) => setDraft((d) => ({ ...d, to: e.target.value }))}
              />
              <FieldError text={errors.to} />
            </label>
          </div>
          <label className="block space-y-1.5">
            <FieldLabel>Նշում</FieldLabel>
            <Input
              value={draft.note}
              placeholder="օր.՝ փոխարինում է Արամը"
              onChange={(e) => setDraft((d) => ({ ...d, note: e.target.value }))}
            />
          </label>
          <Button type="submit" disabled={pending}>
            <CalendarPlus className="h-4 w-4" />
            Ավելացնել
          </Button>
        </form>
      </Card>

      <SectionCard title={`Այսօր բացակա են (${now.length})`}>
        {now.length > 0 ? <AbsenceList absences={now} onRemove={remove} /> : <EmptyCard>Այսօր բոլորն աշխատանքի են։</EmptyCard>}
      </SectionCard>

      <SectionCard
        title={`Առջևում (${ahead.length})`}
        description="Պլանավորված արձակուրդներն ու հանգստյան օրերը։ Դրանք երևում են նաև օրացույցում։"
      >
        {ahead.length > 0 ? (
          <AbsenceList absences={ahead} onRemove={remove} />
        ) : (
          <EmptyCard>Պլանավորված բացակայություններ չկան։</EmptyCard>
        )}
      </SectionCard>

      <SectionCard
        title={`Արձակուրդի օրեր ${data.today.slice(0, 4)} թ.`}
        description="Արձակուրդի օրացուցային օրերը, անցածն ու պլանավորվածը միասին։"
      >
        <TableWrap>
          <Thead>
            <Th>Աշխատակից</Th>
            <Th className="text-right">Օրեր</Th>
          </Thead>
          <tbody>
            {active.map((e) => (
              <Tr key={e.id}>
                <Td>
                  <Link href={`/admin/employees/${e.id}`} className="font-medium text-ink hover:text-primary">
                    {e.name}
                  </Link>
                </Td>
                <Td className="text-right tabular-nums">{data.vacationDays[String(e.id)] ?? 0}</Td>
              </Tr>
            ))}
            {active.length === 0 ? <EmptyRow colSpan={2}>Աշխատակիցներ դեռ չկան։</EmptyRow> : null}
          </tbody>
        </TableWrap>
      </SectionCard>

      {past.length > 0 ? (
        <SectionCard title="Վերջին ամիսը">
          <AbsenceList absences={past} onRemove={remove} />
        </SectionCard>
      ) : null}
    </>
  );
}

function AbsenceList({ absences, onRemove }: { absences: AbsenceDto[]; onRemove: (a: AbsenceDto) => void }) {
  return (
    <ul className="divide-y divide-border">
      {absences.map((a) => (
        <li key={a.id} className="flex items-center gap-3 py-2.5">
          <div className="min-w-0 flex-1">
            <p className="text-sm text-ink">
              {a.employee ? (
                <Link href={`/admin/employees/${a.employee.id}`} className="font-medium hover:text-primary">
                  {a.employee.name}
                </Link>
              ) : (
                "—"
              )}
              <Badge className="ml-2" tone={KINDS.find((k) => k.value === a.kind)?.tone ?? "default"}>
                {a.kindLabel}
              </Badge>
            </p>
            <p className="text-xs text-ink-muted">
              {range(a)} · {a.days} օր{a.note ? ` · ${a.note}` : ""}
            </p>
          </div>
          <Button variant="ghost" size="sm" aria-label="Ջնջել" onClick={() => onRemove(a)}>
            <Trash2 className="h-4 w-4" />
          </Button>
        </li>
      ))}
    </ul>
  );
}

const STAGE_TONES: Record<CandidateStage, BadgeTone> = {
  new: "info",
  screening: "pending",
  interview: "warning",
  offer: "primary",
  hired: "success",
  rejected: "default",
};

const EMPLOYMENT: Record<NonNullable<JobOpeningDto["employment"]>, string> = {
  full_time: "Լրիվ դրույք",
  part_time: "Կես դրույք",
  shift: "Հերթափոխով",
  freelance: "Ազատ գրաֆիկ",
};

const OPENING_STATUS: Record<JobOpeningDto["status"], string> = { open: "Բաց", paused: "Դադարեցված", closed: "Փակ" };

/**
 * Hiring: the jobs the business hires for and the candidates of each, by
 * stage. Postings for job sites are written by Անյա in the chat; the
 * candidates who answered are kept here.
 */
export function HiringBoard({ data }: { data: TeamHiringDto }) {
  const router = useRouter();
  const [opening, setOpening] = useState({ title: "", salary: "", employment: "", description: "" });
  const [candidate, setCandidate] = useState({ name: "", phone: "", openingId: "", source: "", interviewAt: "" });
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [pending, setPending] = useState(false);

  async function addOpening() {
    setPending(true);
    const result = await send("/api/admin/team/openings", "POST", {
      title: opening.title,
      salary: opening.salary || null,
      employment: opening.employment || null,
      description: opening.description || null,
    });
    setPending(false);
    setErrors(result.errors);
    if (!result.ok) return;
    setOpening({ title: "", salary: "", employment: "", description: "" });
    router.refresh();
  }

  async function addCandidate() {
    setPending(true);
    const result = await send("/api/admin/team/candidates", "POST", {
      name: candidate.name,
      phone: candidate.phone || null,
      openingId: candidate.openingId ? Number(candidate.openingId) : null,
      source: candidate.source || null,
      interviewAt: candidate.interviewAt ? candidate.interviewAt.replace("T", " ") : null,
      ...(candidate.interviewAt ? { stage: "interview" } : {}),
    });
    setPending(false);
    setErrors(result.errors);
    if (!result.ok) return;
    setCandidate({ name: "", phone: "", openingId: "", source: "", interviewAt: "" });
    router.refresh();
  }

  const open = data.openings.filter((o) => o.status !== "closed");

  return (
    <>
      <SectionCard
        title={`Հաստիքներ (${open.length})`}
        description="Ում եք փնտրում։ Անյան կգրի հայտարարությունը list.am-ի, job.am-ի, Instagram-ի և Telegram-ի համար։"
      >
        <div className="space-y-4">
          <form
            className="grid items-start gap-3 sm:grid-cols-2 lg:grid-cols-[2fr_1fr_1fr_auto]"
            onSubmit={(e) => {
              e.preventDefault();
              addOpening();
            }}
          >
            <div>
              <Input
                value={opening.title}
                placeholder="Հաստիք, օր.՝ Քիմմաքրման վարպետ"
                onChange={(e) => setOpening((o) => ({ ...o, title: e.target.value }))}
              />
              <FieldError text={errors.title} />
            </div>
            <Input
              value={opening.salary}
              placeholder="Աշխատավարձ"
              onChange={(e) => setOpening((o) => ({ ...o, salary: e.target.value }))}
            />
            <Select value={opening.employment} onChange={(e) => setOpening((o) => ({ ...o, employment: e.target.value }))}>
              <option value="">Գրաֆիկ…</option>
              {Object.entries(EMPLOYMENT).map(([value, label]) => (
                <option key={value} value={value}>
                  {label}
                </option>
              ))}
            </Select>
            <Button type="submit" variant="secondary" disabled={pending}>
              <BriefcaseBusiness className="h-4 w-4" />
              Ավելացնել
            </Button>
            <Textarea
              className="sm:col-span-2 lg:col-span-4"
              rows={2}
              value={opening.description}
              placeholder="Պահանջներ և պայմաններ (ըստ ցանկության)"
              onChange={(e) => setOpening((o) => ({ ...o, description: e.target.value }))}
            />
          </form>

          {data.openings.length > 0 ? (
            <div className="grid gap-3 md:grid-cols-2">
              {data.openings.map((o) => (
                <OpeningCard key={o.id} opening={o} candidates={data.candidates.filter((c) => c.openingId === o.id)} />
              ))}
            </div>
          ) : null}
        </div>
      </SectionCard>

      <SectionCard
        title={`Թեկնածուներ (${data.candidates.length})`}
        description="Ով է արձագանքել։ Փոխեք փուլը, երբ զանգահարեք, հրավիրեք հարցազրույցի կամ ընդունեք աշխատանքի։"
      >
        <div className="space-y-4">
          <form
            className="grid items-start gap-3 sm:grid-cols-2 lg:grid-cols-[1.4fr_1fr_1.2fr_1fr_1.2fr_auto]"
            onSubmit={(e) => {
              e.preventDefault();
              addCandidate();
            }}
          >
            <div>
              <Input
                value={candidate.name}
                placeholder="Անուն *"
                onChange={(e) => setCandidate((c) => ({ ...c, name: e.target.value }))}
              />
              <FieldError text={errors.name} />
            </div>
            <Input
              value={candidate.phone}
              placeholder="Հեռախոս"
              onChange={(e) => setCandidate((c) => ({ ...c, phone: e.target.value }))}
            />
            <div>
              <Select value={candidate.openingId} onChange={(e) => setCandidate((c) => ({ ...c, openingId: e.target.value }))}>
                <option value="">Հաստիք…</option>
                {open.map((o) => (
                  <option key={o.id} value={o.id}>
                    {o.title}
                  </option>
                ))}
              </Select>
              <FieldError text={errors.openingId} />
            </div>
            <Input
              value={candidate.source}
              placeholder="Աղբյուր (list.am…)"
              onChange={(e) => setCandidate((c) => ({ ...c, source: e.target.value }))}
            />
            <div>
              <Input
                type="datetime-local"
                aria-label="Հարցազրույց"
                value={candidate.interviewAt}
                onChange={(e) => setCandidate((c) => ({ ...c, interviewAt: e.target.value }))}
              />
              <FieldError text={errors.interviewAt} />
            </div>
            <Button type="submit" variant="secondary" disabled={pending}>
              <UserPlus className="h-4 w-4" />
              Ավելացնել
            </Button>
          </form>

          {data.candidates.length > 0 ? (
            <div className="space-y-5">
              {data.stages.map((stage) => {
                const list = data.candidates.filter((c) => c.stage === stage.value);
                if (list.length === 0) return null;
                return (
                  <div key={stage.value} className="space-y-2">
                    <h3 className="flex items-center gap-2 text-sm font-semibold text-ink">
                      <Badge tone={STAGE_TONES[stage.value]}>{stage.label}</Badge>
                      <span className="text-ink-muted">{list.length}</span>
                    </h3>
                    <ul className="divide-y divide-border rounded-lg border border-border">
                      {list.map((c) => (
                        <CandidateRow key={c.id} candidate={c} data={data} />
                      ))}
                    </ul>
                  </div>
                );
              })}
            </div>
          ) : (
            <EmptyCard>Թեկնածուներ դեռ չկան։ Ավելացրեք նրանց, ովքեր արձագանքել են հայտարարությանը։</EmptyCard>
          )}
        </div>
      </SectionCard>
    </>
  );
}

function OpeningCard({ opening, candidates }: { opening: JobOpeningDto; candidates: CandidateDto[] }) {
  const router = useRouter();

  async function setStatus(status: JobOpeningDto["status"]) {
    if ((await send(`/api/admin/team/openings/${opening.id}`, "PATCH", { status })).ok) router.refresh();
  }

  async function remove() {
    const note = candidates.length > 0 ? ` Նրա ${candidates.length} թեկնածուները կմնան, առանց հաստիքի։` : "";
    if (!confirm(`Ջնջե՞լ «${opening.title}» հաստիքը։${note}`)) return;
    if ((await send(`/api/admin/team/openings/${opening.id}`, "DELETE")).ok) router.refresh();
  }

  const inProgress = candidates.filter((c) => c.stage !== "hired" && c.stage !== "rejected").length;

  return (
    <Card className={opening.status === "closed" ? "opacity-60" : undefined}>
      <div className="flex items-start gap-3">
        <div className="min-w-0 flex-1">
          <p className="font-medium text-ink">{opening.title}</p>
          <p className="mt-0.5 text-xs text-ink-muted">
            {[opening.salary, opening.employment ? EMPLOYMENT[opening.employment] : null, `${inProgress} թեկնածու ընթացքում`]
              .filter(Boolean)
              .join(" · ")}
          </p>
          {opening.description ? (
            <p className="mt-2 line-clamp-3 whitespace-pre-line text-sm text-ink-muted">{opening.description}</p>
          ) : null}
        </div>
        <Button variant="ghost" size="sm" aria-label="Ջնջել" onClick={remove}>
          <Trash2 className="h-4 w-4" />
        </Button>
      </div>
      <div className="mt-3">
        <Select
          aria-label="Կարգավիճակ"
          value={opening.status}
          onChange={(e) => setStatus(e.target.value as JobOpeningDto["status"])}
          className="!w-40"
        >
          {Object.entries(OPENING_STATUS).map(([value, label]) => (
            <option key={value} value={value}>
              {label}
            </option>
          ))}
        </Select>
      </div>
    </Card>
  );
}

function CandidateRow({ candidate, data }: { candidate: CandidateDto; data: TeamHiringDto }) {
  const router = useRouter();
  const opening = data.openings.find((o) => o.id === candidate.openingId);

  async function update(body: Record<string, unknown>) {
    if ((await send(`/api/admin/team/candidates/${candidate.id}`, "PATCH", body)).ok) router.refresh();
  }

  async function remove() {
    if (!confirm(`Ջնջե՞լ «${candidate.name}» թեկնածուին։`)) return;
    if ((await send(`/api/admin/team/candidates/${candidate.id}`, "DELETE")).ok) router.refresh();
  }

  return (
    <li className="flex flex-wrap items-center gap-x-4 gap-y-2 px-3 py-2.5">
      <div className="min-w-0 flex-1 basis-56">
        <p className="text-sm font-medium text-ink">
          {candidate.employeeId ? (
            <Link href={`/admin/employees/${candidate.employeeId}`} className="hover:text-primary">
              {candidate.name}
            </Link>
          ) : (
            candidate.name
          )}
          {candidate.rating ? <span className="ml-2 text-xs text-warning">{"★".repeat(candidate.rating)}</span> : null}
        </p>
        <p className="flex flex-wrap items-center gap-x-3 text-xs text-ink-muted">
          {opening ? <span>{opening.title}</span> : null}
          {candidate.source ? <span>{candidate.source}</span> : null}
          {candidate.phone ? (
            <a href={`tel:${candidate.phone}`} className="inline-flex items-center gap-1 hover:text-primary">
              <Phone className="h-3 w-3" />
              {candidate.phone}
            </a>
          ) : null}
          {candidate.email ? (
            <a href={`mailto:${candidate.email}`} className="inline-flex items-center gap-1 hover:text-primary">
              <Mail className="h-3 w-3" />
              {candidate.email}
            </a>
          ) : null}
        </p>
        {candidate.notes ? <p className="mt-1 text-xs text-ink-faint">{candidate.notes}</p> : null}
      </div>
      <Input
        type="datetime-local"
        aria-label="Հարցազրույց"
        className="!w-52"
        defaultValue={candidate.interviewAt?.replace(" ", "T") ?? ""}
        onBlur={(e) => {
          const value = e.target.value ? e.target.value.replace("T", " ") : null;
          if (value !== (candidate.interviewAt ?? null)) update({ interviewAt: value });
        }}
      />
      <Select aria-label="Փուլ" className="!w-40" value={candidate.stage} onChange={(e) => update({ stage: e.target.value })}>
        {data.stages.map((s) => (
          <option key={s.value} value={s.value}>
            {s.label}
          </option>
        ))}
      </Select>
      <Button variant="ghost" size="sm" aria-label="Ջնջել" onClick={remove}>
        <Trash2 className="h-4 w-4" />
      </Button>
    </li>
  );
}
