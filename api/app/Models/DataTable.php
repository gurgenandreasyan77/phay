<?php

namespace App\Models;

use App\DataTables\DataColumn;
use App\Enums\DataTableSection;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A table a business designed itself (see the create_data_tables
 * migration). `columns` is a list of {key, label, type, required, options}.
 */
class DataTable extends Model
{
    use BelongsToTenant;

    protected $fillable = ['section', 'name', 'description', 'columns', 'sort_order'];

    protected $casts = [
        'section' => DataTableSection::class,
        'columns' => 'array',
        'sort_order' => 'integer',
    ];

    public function rows(): HasMany
    {
        return $this->hasMany(DataTableRow::class);
    }

    /** @return list<DataColumn> */
    public function columnDefinitions(): array
    {
        return array_map(DataColumn::fromArray(...), $this->columns ?? []);
    }
}
