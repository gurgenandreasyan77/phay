<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A team of employees led by a manager. The manager is an employee as well
 * and may lead several groups.
 */
class EmployeeGroup extends Model
{
    use BelongsToTenant;

    protected $fillable = ['name', 'manager_id'];

    protected $casts = [
        'manager_id' => 'integer',
    ];

    public function manager(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'manager_id');
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class, 'group_id');
    }
}
