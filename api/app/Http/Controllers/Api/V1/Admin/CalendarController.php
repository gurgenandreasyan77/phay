<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\DataTables\PeriodTables;
use App\Enums\DataColumnType;
use App\Enums\DataTableSection;
use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\DataTableResource;
use App\Models\DataTable;
use App\Models\DataTableRow;
use App\Models\Lead;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The orders of a few weeks by day: rows of the business's order tables
 * that have a date column (by their occurred_at, the business's local
 * time), and the site's orders by the date the customer asked for. Not a
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
        $tables = DataTable::query()
            ->where('section', DataTableSection::Orders)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->filter(fn (DataTable $table) => $table->dateColumn() !== null)
            ->values();
        PeriodTables::infer($tables);

        $rows = DataTableRow::query()
            ->whereIn('data_table_id', $tables->modelKeys())
            ->where('occurred_at', '>=', $from->format('Y-m-d H:i:s'))
            ->where('occurred_at', '<', $to->addDay()->format('Y-m-d H:i:s'))
            ->orderBy('occurred_at')
            ->orderBy('id')
            ->limit(self::MAX_ROWS + 1)
            ->get(['id', 'data_table_id', 'data', 'occurred_at', 'amount']);

        $timed = $tables->mapWithKeys(fn (DataTable $table) => [$table->id => $table->dateColumn()->type === DataColumnType::DateTime]);

        $leads = Lead::query()
            ->with('service')
            ->whereNotNull('preferred_date')
            // Not whereBetween: a date may be stored with a time (SQLite), and the last day counts.
            ->where('preferred_date', '>=', $from->toDateString())
            ->where('preferred_date', '<', $to->addDay()->toDateString())
            ->orderBy('preferred_date')
            ->orderBy('id')
            ->get();

        return response()->json(['data' => [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'tables' => DataTableResource::collection($tables),
            // The period of each table kept per day, week, month, quarter or
            // year, by table id (each table also has it as periodInfo): an
            // order added on a day of another period goes to that period's table.
            'periods' => (object) $tables->filter(fn (DataTable $t) => $t->period() !== null)
                ->mapWithKeys(fn (DataTable $t) => [$t->id => $t->period()->toArray()])
                ->all(),
            'rows' => $rows->take(self::MAX_ROWS)->map(function (DataTableRow $row) use ($timed) {
                $at = CarbonImmutable::parse($row->getRawOriginal('occurred_at'));

                return [
                    'id' => $row->id,
                    'tableId' => $row->data_table_id,
                    'date' => $at->toDateString(),
                    // Only a date-and-time column has a time; a date column's rows are "all day".
                    'time' => $timed[$row->data_table_id] ? $at->format('H:i') : null,
                    'amount' => $row->amount,
                    'values' => (object) ($row->data ?? []),
                ];
            })->values(),
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
        ]]);
    }
}
