<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\DataTables\Changes\ChangeRefused;
use App\DataTables\Changes\TableChanges;
use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Models\TableChange;
use Closure;
use Illuminate\Http\JsonResponse;

/**
 * Changes of the tables an AI agent prepared in the chat (TableChanges):
 * the card reads one here, and the owner applies it, discards it, or
 * undoes it while nobody touched what it changed. Owner and admin (they
 * design the tables); expenses tables only for those who see them.
 */
class TableChangeController extends Controller
{
    public function show(TableChange $tableChange, TableChanges $changes): JsonResponse
    {
        $this->ensureReachable($tableChange);

        return response()->json(['data' => $changes->describe($tableChange)]);
    }

    public function apply(TableChange $tableChange, TableChanges $changes): JsonResponse
    {
        $this->ensureReachable($tableChange);

        return $this->refusing(fn () => response()->json(['data' => $changes->describe($changes->apply($tableChange))]));
    }

    public function undo(TableChange $tableChange, TableChanges $changes): JsonResponse
    {
        $this->ensureReachable($tableChange);

        return $this->refusing(fn () => response()->json(['data' => $changes->describe($changes->undo($tableChange))]));
    }

    public function discard(TableChange $tableChange, TableChanges $changes): JsonResponse
    {
        $this->ensureReachable($tableChange);

        return $this->refusing(fn () => response()->json(['data' => $changes->describe($changes->discard($tableChange))]));
    }

    private function ensureReachable(TableChange $change): void
    {
        abort_unless(DataTableController::sectionAllowed($change->section), 404);
    }

    /** @param  Closure(): JsonResponse  $action */
    private function refusing(Closure $action): JsonResponse
    {
        try {
            return $action();
        } catch (ChangeRefused $e) {
            throw new ApiException(422, 'change_refused', $e->getMessage(), $e);
        }
    }
}
