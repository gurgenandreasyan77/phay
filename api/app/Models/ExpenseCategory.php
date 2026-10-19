<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A kind of a business's expenses: a ready-made one (key set, see
 * App\Expenses\ExpenseCategories) or the business's own. A row of an
 * expenses table is in it when its category column (ColumnRole::Category)
 * holds its name. in_profit: whether the net profit subtracts it.
 */
class ExpenseCategory extends Model
{
    use BelongsToTenant;

    protected $fillable = ['key', 'name', 'in_profit', 'sort_order'];

    protected $casts = [
        'in_profit' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function recurring(): HasMany
    {
        return $this->hasMany(RecurringExpense::class);
    }
}
