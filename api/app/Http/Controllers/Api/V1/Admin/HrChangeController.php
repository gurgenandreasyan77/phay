<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Models\HrChange;
use App\Team\HrChanges;
use App\Team\HrRefused;
use Closure;
use Illuminate\Http\JsonResponse;

/**
 * Changes of the team's records the HR agent prepared in the chat
 * (HrChanges): the card reads one here, and the owner applies, discards or
 * undoes it. Owner and admin.
 */
class HrChangeController extends Controller
{
    public function show(HrChange $hrChange, HrChanges $changes): JsonResponse
    {
        return response()->json(['data' => $changes->describe($hrChange)]);
    }

    public function apply(HrChange $hrChange, HrChanges $changes): JsonResponse
    {
        return $this->refusing(fn () => response()->json(['data' => $changes->describe($changes->apply($hrChange))]));
    }

    public function undo(HrChange $hrChange, HrChanges $changes): JsonResponse
    {
        return $this->refusing(fn () => response()->json(['data' => $changes->describe($changes->undo($hrChange))]));
    }

    public function discard(HrChange $hrChange, HrChanges $changes): JsonResponse
    {
        return $this->refusing(fn () => response()->json(['data' => $changes->describe($changes->discard($hrChange))]));
    }

    /** @param  Closure(): JsonResponse  $action */
    private function refusing(Closure $action): JsonResponse
    {
        try {
            return $action();
        } catch (HrRefused $e) {
            throw new ApiException(422, 'change_refused', $e->getMessage(), $e);
        }
    }
}
