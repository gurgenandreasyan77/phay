<?php

namespace App\DataTables;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Leaves out the older versions of a table that a newer one replaced (a
 * table remade from the Google calendars): the calendar, analytics, finance
 * and the AI assistant count each order once. The tables list and a
 * table's own page still reach them (DataTable::withArchived()).
 */
class CurrentTableScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $builder->whereNull($model->qualifyColumn('archived_at'));
    }
}
