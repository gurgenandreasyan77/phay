<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Someone who does a business's orders. A row is theirs when its performer
 * column (ColumnRole::Performer) holds their name: the link is kept on the
 * row (employee_id, see RowMetrics), so the employees page counts orders,
 * revenue and costs per person without reading every row's data.
 */
class Employee extends Model
{
    use BelongsToTenant;

    protected $fillable = ['name', 'position', 'phone', 'is_active'];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function rows(): HasMany
    {
        return $this->hasMany(DataTableRow::class);
    }

    /** How a performer cell is matched to a name: case and spaces aside. */
    public static function normalize(?string $name): string
    {
        return mb_strtolower(preg_replace('/\s+/u', ' ', trim((string) $name)));
    }
}
