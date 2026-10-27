<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\DataTables\Duplicates\RowDuplicates;
use App\DataTables\PeriodTables;
use App\Enums\BusinessRole;
use App\Enums\ColumnRole;
use App\Enums\DataColumnType;
use App\Enums\DataTableSection;
use App\Google\EventFields;
use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\DataTableResource;
use App\Models\Candidate;
use App\Models\DataTable;
use App\Models\DataTableRow;
use App\Models\EmployeeAbsence;
use App\Models\GoogleEvent;
use App\Models\Lead;
use App\Team\Team;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The orders of a few weeks by day: rows of the business's order and sales
 * tables that have a date column (by their occurred_at, the business's
 * local time; each row says its section, so the calendar shows services
 * and sales apart), and the site's orders by the date the customer asked
 * for. Not a
 * store of its own: the calendar adds and deletes rows through the rows API,
 * so analytics and the AI assistant always see what the calendar shows.
 */
class CalendarController extends Controller
{
    /** A month grid is six weeks; a little more for a range that starts mid-week. */
    public const MAX_DAYS = 62;

    /** Enough for a busy month; a calendar showing a cut list says so (`truncated`). */
    public const MAX_ROWS = 3000;

    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);

        $from = CarbonImmutable::parse($validated['from'])->startOfDay();
        $to = CarbonImmutable::parse($validated['to'])->startOfDay();
        abort_if($from->diffInDays($to) >= self::MAX_DAYS, 422, 'The range is too long.');

        // Tables without a date column can't be put on days.
        $sections = array_values(array_filter(
            [DataTableSection::Orders, DataTableSection::Sales],
            fn (DataTableSection $section) => DataTableController::sectionAllowed($section),
        ));
        $tables = DataTable::query()
            ->whereIn('section', array_map(fn (DataTableSection $s) => $s->value, $sections))
            ->orderByRaw("CASE WHEN section = 'orders' THEN 0 ELSE 1 END")
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->filter(fn (DataTable $table) => $table->dateColumn() !== null)
            ->values();
        PeriodTables::infer($tables);

        $all = DataTableRow::query()
            ->whereIn('data_table_id', $tables->modelKeys())
            ->where('occurred_at', '>=', $from->format('Y-m-d H:i:s'))
            ->where('occurred_at', '<', $to->addDay()->format('Y-m-d H:i:s'))
            ->orderBy('occurred_at')
            ->orderBy('id')
            ->limit(self::MAX_ROWS * 2)
            ->get(['id', 'data_table_id', 'data', 'occurred_at', 'amount', 'source', 'external_id', 'duplicate_of_id']);
        // The same order in several tables is shown once, saying in how many it is.
        $copies = $all->whereNotNull('duplicate_of_id')->countBy('duplicate_of_id');
        $rows = $all->whereNull('duplicate_of_id')->take(self::MAX_ROWS + 1)->values();
        $rows->each(fn (DataTableRow $row) => $row->setRelation('table', $tables->firstWhere('id', $row->data_table_id)));
        // Possible copies the owner hasn't settled (one row lacks a field the other has).
        $review = app(RowDuplicates::class)->review(
            null,
            200,
            $all->map(fn (DataTableRow $r) => substr((string) $r->getRawOriginal('occurred_at'), 0, 10))->unique()->values()->all(),
            fn (DataTableSection $section) => DataTableController::sectionAllowed($section),
        );
        $possible = [];
        foreach ($review as $group) {
            foreach ($group['rows'] as $member) {
                if ($member['copyOf'] === null) {
                    $possible[$member['id']] = $group['key'];
                }
            }
        }

        $timed = $tables->mapWithKeys(fn (DataTable $table) => [$table->id => $table->dateColumn()->type === DataColumnType::DateTime]);
        // A date column with the time in a column of its own (ColumnRole::Time): a row whose time is filled.
        $clocked = $tables->mapWithKeys(fn (DataTable $table) => [$table->id => $table->roleColumn(ColumnRole::Time) !== null]);
        $sectionOf = $tables->mapWithKeys(fn (DataTable $table) => [$table->id => $table->section->value]);

        $leads = Lead::query()
            ->with('service')
            ->whereNotNull('preferred_date')
            // Not whereBetween: a date may be stored with a time (SQLite), and the last day counts.
            ->where('preferred_date', '>=', $from->toDateString())
            ->where('preferred_date', '<', $to->addDay()->toDateString())
            ->orderBy('preferred_date')
            ->orderBy('id')
            ->get();

        // Events of the synced Google calendars (everyone's in the business),
        // except those already turned into rows of a table shown here: by the
        // event's id, or the same start and fields (a table copied by hand,
        // a month's table made from the Google one).
        $inTables = $all->pluck('external_id')->filter()->flip();
        $duplicates = app(RowDuplicates::class);
        $orderRowsByDay = $rows->filter(fn (DataTableRow $r) => $sectionOf[$r->data_table_id] === DataTableSection::Orders->value)
            ->groupBy(fn (DataTableRow $r) => substr((string) $r->getRawOriginal('occurred_at'), 0, 10));
        $events = GoogleEvent::query()
            ->with('calendar:id,name,color,google_connection_id', 'calendar.connection:id,email')
            ->whereHas('calendar', fn ($q) => $q->where('selected', true))
            ->where('starts_at', '>=', $from->format('Y-m-d H:i:s'))
            ->where('starts_at', '<', $to->addDay()->format('Y-m-d H:i:s'))
            ->orderBy('starts_at')
            ->orderBy('id')
            ->limit(self::MAX_ROWS)
            ->get()
            ->unique('event_id')
            ->reject(fn (GoogleEvent $event) => $inTables->has($event->event_id) || $this->isRow($event, $orderRowsByDay, $duplicates))
            ->values();

        // Who is away (everyone working with orders plans around it), and the
        // interviews with candidates (only those who manage the team).
        $absences = EmployeeAbsence::query()->with('employee:id,name')
            ->overlapping($from->toDateString(), $to->toDateString())
            ->orderBy('starts_on')->orderBy('id')
            ->get();
        $team = $request->user()->businessRole(app(TenantContext::class)->current())?->can(BusinessRole::SETTINGS);
        $interviews = $team ? Candidate::query()->with('opening:id,title')
            ->whereNotNull('interview_at')
            ->where('interview_at', '>=', $from->format('Y-m-d H:i:s'))
            ->where('interview_at', '<', $to->addDay()->format('Y-m-d H:i:s'))
            ->orderBy('interview_at')
            ->get() : collect();

        return response()->json(['data' => [
            'absences' => $absences->map(fn (EmployeeAbsence $a) => Team::absence($a))->values(),
            'interviews' => $interviews->map(fn (Candidate $c) => [
                'id' => $c->id,
                'date' => substr($c->interviewTime(), 0, 10),
                'time' => substr($c->interviewTime(), 11, 5),
                'name' => $c->name,
                'opening' => $c->opening?->title,
                'stage' => $c->stage->value,
            ])->values(),
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'tables' => DataTableResource::collection($tables),
            // The period of each table kept per day, week, month, quarter or
            // year, by table id (each table also has it as periodInfo): an
            // order added on a day of another period goes to that period's table.
            'periods' => (object) $tables->filter(fn (DataTable $t) => $t->period() !== null)
                ->mapWithKeys(fn (DataTable $t) => [$t->id => $t->period()->toArray()])
                ->all(),
            'rows' => $rows->take(self::MAX_ROWS)->map(function (DataTableRow $row) use ($timed, $clocked, $sectionOf, $copies, $possible) {
                $at = CarbonImmutable::parse($row->getRawOriginal('occurred_at'));
                $time = $timed[$row->data_table_id] || ($clocked[$row->data_table_id] && $at->format('H:i') !== '00:00');

                return [
                    'id' => $row->id,
                    'tableId' => $row->data_table_id,
                    // orders (a service done) or sales (goods sold): shown apart.
                    'section' => $sectionOf[$row->data_table_id],
                    'date' => $at->toDateString(),
                    // Only a date-and-time column has a time; a date column's rows are "all day".
                    'time' => $time ? $at->format('H:i') : null,
                    'amount' => $row->amount,
                    'values' => (object) ($row->data ?? []),
                    // "google": made from a Google Calendar event.
                    'source' => $row->source,
                    // The same order is in that many other tables too (counted once).
                    'copies' => $copies[$row->id] ?? 0,
                    // May be the same as a row of another table: the owner is asked.
                    'possibleDuplicate' => $possible[$row->id] ?? null,
                ];
            })->values(),
            // Groups of rows (of this range) that are or may be one record.
            'duplicates' => count($review),
            'truncated' => $rows->count() > self::MAX_ROWS,
            'leads' => $leads->map(fn (Lead $lead) => [
                'id' => $lead->id,
                'date' => $lead->preferred_date->toDateString(),
                'name' => $lead->name,
                'phone' => $lead->phone,
                'service' => $lead->service?->trans('name', 'hy'),
                'status' => $lead->status->value,
                'finalPrice' => $lead->final_price,
            ])->values(),
            'google' => $events->map(function (GoogleEvent $event) {
                $at = CarbonImmutable::parse($event->getRawOriginal('starts_at'));

                return [
                    'id' => $event->id,
                    'eventId' => $event->event_id,
                    'date' => $at->toDateString(),
                    'time' => $event->all_day ? null : $at->format('H:i'),
                    'end' => $event->ends_at && ! $event->all_day ? CarbonImmutable::parse($event->getRawOriginal('ends_at'))->format('H:i') : null,
                    'title' => $event->summary ?? 'Առանց անվանման',
                    'location' => $event->location,
                    'description' => $event->description ? mb_substr($event->description, 0, 300) : null,
                    'calendar' => $event->calendar->name,
                    'color' => $event->calendar->color,
                    'account' => $event->calendar->connection?->email,
                    'link' => str_starts_with((string) $event->html_link, 'https://') ? $event->html_link : null,
                ];
            })->values(),
        ]]);
    }

    /** A synced event that is already a row of an orders table (the same start and fields). */
    private function isRow(GoogleEvent $event, $rowsByDay, RowDuplicates $duplicates): bool
    {
        $at = CarbonImmutable::parse($event->getRawOriginal('starts_at'));
        $rows = $rowsByDay->get($at->toDateString());
        if ($rows === null) {
            return false;
        }
        $fields = EventFields::of($event);
        $amount = $fields[ColumnRole::Amount->value] ?? null;
        $amount = is_numeric($amount = str_replace([' ', ','], ['', '.'], (string) $amount)) ? (float) $amount : null;
        unset($fields[ColumnRole::Amount->value]);

        return $duplicates->eventIsRow($fields, $event->all_day ? null : $at->format('H:i'), $amount, $rows);
    }
}
