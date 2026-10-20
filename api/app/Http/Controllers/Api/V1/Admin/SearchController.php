<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\DataTables\DataColumn;
use App\Enums\BusinessRole;
use App\Enums\DataColumnType;
use App\Enums\DataTableSection;
use App\Features\FeatureFlag;
use App\Http\Controllers\Controller;
use App\Models\DataTable;
use App\Models\DataTableRow;
use App\Models\Employee;
use App\Models\Lead;
use App\Models\Service;
use App\Models\Tenant;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Laravel\Pennant\Feature;

/**
 * The search in the admin's header: one query over what the person may open
 * in the business they are in (its tables and their rows, site orders,
 * employees, services) and, for platform staff, the businesses. Each group
 * follows the same role and module rules as its own page, and every result
 * links to the page that shows it.
 */
class SearchController extends Controller
{
    /** Results per group: enough to find it, short enough to read. */
    public const PER_GROUP = 6;

    public function __construct(private readonly TenantContext $context) {}

    public function __invoke(Request $request): JsonResponse
    {
        $request->validate(['q' => ['required', 'string', 'min:2', 'max:100']]);

        $query = trim((string) $request->input('q'));
        $needle = mb_strtolower($query);
        $user = $request->user();
        $role = $user->businessRole($this->context->current());

        $groups = [];
        if (mb_strlen($needle) >= 2) {
            $sections = collect(DataTableSection::cases())
                ->filter(fn (DataTableSection $section) => DataTableController::sectionAllowed($section))
                ->values();
            $groups = [
                'tables' => $sections->isEmpty() ? [] : $this->tables($sections, $needle),
                'rows' => $sections->isEmpty() ? [] : $this->rows($sections, $query, $needle),
                'leads' => $role?->can(BusinessRole::ORDERS)
                    ? $this->leads($needle) : [],
                'employees' => $role?->can(BusinessRole::SETTINGS) ? $this->employees($needle) : [],
                'services' => $role?->can(BusinessRole::CATALOG) && Feature::active(FeatureFlag::Services->value)
                    ? $this->services($needle) : [],
                'businesses' => $user->isPlatformStaff() ? $this->businesses($needle) : [],
            ];
        }

        return response()->json(['data' => [
            'query' => $query,
            'groups' => collect($groups)
                ->filter(fn (array $items) => $items !== [])
                ->map(fn (array $items, string $key) => ['key' => $key, 'items' => $items])
                ->values(),
        ]]);
    }

    /** @param Collection<int, DataTableSection> $sections */
    private function tables(Collection $sections, string $needle): array
    {
        return DataTable::query()
            ->whereIn('section', $sections->map->value)
            ->whereRaw('LOWER(name) LIKE ?', [$this->like($needle)])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->limit(self::PER_GROUP)
            ->get()
            ->map(fn (DataTable $table) => [
                'id' => $table->id,
                'title' => $table->name,
                'subtitle' => $this->sectionLabel($table->section),
                'url' => "/admin/{$table->section->value}/tables/{$table->id}",
            ])->all();
    }

    /**
     * Rows whose any value contains the text (as the table page's own
     * search does), shown by their first few values.
     *
     * @param  Collection<int, DataTableSection>  $sections
     */
    private function rows(Collection $sections, string $query, string $needle): array
    {
        $tables = DataTable::query()->whereIn('section', $sections->map->value)->get()->keyBy('id');
        if ($tables->isEmpty()) {
            return [];
        }

        return DataTableRow::query()
            ->whereIn('data_table_id', $tables->keys())
            ->whereRaw('LOWER(CAST(data AS TEXT)) LIKE ?', [$this->like($needle)])
            ->orderByDesc('id')
            ->limit(self::PER_GROUP)
            ->get(['id', 'data_table_id', 'data'])
            ->map(function (DataTableRow $row) use ($tables, $query) {
                $table = $tables[$row->data_table_id];
                $data = $row->data ?? [];
                $values = collect($table->columnDefinitions())
                    ->map(fn (DataColumn $column) => $this->display($column, $data[$column->key] ?? null))
                    ->filter(fn (?string $value) => $value !== null && $value !== '');

                return [
                    'id' => $row->id,
                    'title' => Str::limit($values->take(3)->implode(' · '), 120) ?: $table->name,
                    'subtitle' => $this->sectionLabel($table->section).' · '.$table->name,
                    'url' => "/admin/{$table->section->value}/tables/{$table->id}?search=".rawurlencode($query),
                ];
            })->all();
    }

    /** A stored value as a person reads it: 19.10.2026 14:30, Այո. */
    private function display(DataColumn $column, mixed $value): ?string
    {
        if (! is_scalar($value) || trim((string) $value) === '') {
            return null;
        }

        return match (true) {
            is_bool($value) => $value ? 'Այո' : 'Ոչ',
            $column->type === DataColumnType::Date => $this->date((string) $value, 'd.m.Y'),
            $column->type === DataColumnType::DateTime => $this->date((string) $value, 'd.m.Y H:i'),
            default => trim((string) $value),
        };
    }

    private function date(string $value, string $format): string
    {
        try {
            return CarbonImmutable::parse($value)->format($format);
        } catch (\Throwable) {
            return $value;
        }
    }

    private function leads(string $needle): array
    {
        $like = $this->like($needle);
        // A local number (055...) is stored with or without the leading 0 or +374.
        $digits = ltrim((string) preg_replace('/\D+/', '', $needle), '0');

        return Lead::query()
            ->with('service')
            ->where(function (Builder $q) use ($like, $digits) {
                $q->whereRaw('LOWER(name) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(phone) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(email) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(address) LIKE ?', [$like]);
                // "055 33 76 33" finds "+37455337633".
                if (strlen($digits) >= 3) {
                    $q->orWhereRaw("REPLACE(REPLACE(REPLACE(REPLACE(phone, ' ', ''), '-', ''), '+', ''), '(', '') LIKE ?", ['%'.$digits.'%']);
                }
            })
            ->orderByDesc('created_at')
            ->limit(self::PER_GROUP)
            ->get()
            ->map(fn (Lead $lead) => [
                'id' => $lead->id,
                'title' => $lead->name ?: $lead->phone,
                'subtitle' => collect([$lead->phone, $lead->service?->trans('name', 'hy'), $lead->created_at?->format('d.m.Y')])->filter()->implode(' · '),
                'url' => '/admin/orders/site?search='.rawurlencode((string) ($lead->phone ?: $lead->name)),
            ])->all();
    }

    private function employees(string $needle): array
    {
        $like = $this->like($needle);

        return Employee::query()
            ->where(fn (Builder $q) => $q
                ->whereRaw('LOWER(name) LIKE ?', [$like])
                ->orWhereRaw('LOWER(position) LIKE ?', [$like])
                ->orWhereRaw('LOWER(phone) LIKE ?', [$like]))
            ->orderBy('name')
            ->limit(self::PER_GROUP)
            ->get()
            ->map(fn (Employee $employee) => [
                'id' => $employee->id,
                'title' => $employee->name,
                'subtitle' => collect([$employee->position, $employee->phone])->filter()->implode(' · '),
                'url' => "/admin/employees/{$employee->id}",
            ])->all();
    }

    /** Names are translated (hy/ru/en), so matched in PHP: a business has tens of services. */
    private function services(string $needle): array
    {
        $url = '/admin/services';

        return Service::query()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->filter(fn (Service $service) => collect((array) $service->name)->merge([$service->slug])
                ->contains(fn ($name) => is_string($name) && str_contains(mb_strtolower($name), $needle)))
            ->take(self::PER_GROUP)
            ->map(fn (Service $service) => [
                'id' => $service->id,
                'title' => $service->trans('name', 'hy'),
                'subtitle' => null,
                'url' => $url,
            ])->values()->all();
    }

    private function businesses(string $needle): array
    {
        return Tenant::query()
            ->with(['siteSetting', 'contactChannels'])
            ->orderBy('id')
            ->get()
            ->filter(fn (Tenant $tenant) => TenantController::matches($tenant, $needle))
            ->take(self::PER_GROUP)
            ->map(fn (Tenant $tenant) => [
                'id' => $tenant->id,
                'title' => $tenant->name,
                'subtitle' => $tenant->slug,
                'url' => '/admin/platform?q='.rawurlencode($tenant->name),
            ])->values()->all();
    }

    private function sectionLabel(DataTableSection $section): string
    {
        return match ($section) {
            DataTableSection::Orders => 'Պատվերներ',
            DataTableSection::Sales => 'Վաճառքներ',
            DataTableSection::Expenses => 'Ծախսեր',
        };
    }

    private function like(string $needle): string
    {
        return '%'.addcslashes($needle, '%_\\').'%';
    }
}
