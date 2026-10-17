<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreLeadRequest;
use App\Http\Resources\LeadResource;
use App\Services\LeadService;
use Illuminate\Http\JsonResponse;

class LeadController extends Controller
{
    public function store(StoreLeadRequest $request, LeadService $leads): JsonResponse
    {
        // Honeypot: a hidden field real visitors never fill in. Bots that do
        // get a normal-looking success response, but nothing is persisted.
        if ($request->filled('website')) {
            return response()->json(['data' => ['status' => 'received']], 201);
        }

        $lead = $leads->create($request->validated());

        return (new LeadResource($lead))->response()->setStatusCode(201);
    }
}
