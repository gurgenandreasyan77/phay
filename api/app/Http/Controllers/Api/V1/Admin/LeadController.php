<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\LeadStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreLeadRequest;
use App\Http\Requests\Admin\UpdateLeadRequest;
use App\Http\Resources\Admin\LeadResource;
use App\Models\Lead;
use App\Models\Product;
use App\Models\Service;
use App\Models\ServicePackage;
use App\Services\LeadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;

class LeadController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'status' => ['nullable', Rule::enum(LeadStatus::class)],
            'search' => ['nullable', 'string', 'max:255'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $leads = Lead::query()
            ->with(['service', 'product', 'package'])
            ->when($request->string('status')->toString(), fn ($q, $status) => $q->where('status', $status))
            ->when($request->string('search')->toString(), function ($q, $search) {
                $needle = '%'.mb_strtolower($search).'%';
                $q->where(function ($q2) use ($needle) {
                    $q2->whereRaw('LOWER(name) LIKE ?', [$needle])
                        ->orWhereRaw('LOWER(phone) LIKE ?', [$needle])
                        ->orWhereRaw('LOWER(email) LIKE ?', [$needle]);
                });
            })
            ->orderByDesc('created_at')
            ->paginate(20);

        return LeadResource::collection($leads)->response();
    }

    public function store(StoreLeadRequest $request, LeadService $leads): JsonResponse
    {
        $validated = $request->validated();
        $validated['source'] = $validated['source'] ?? 'manual';
        $validated['preferred_locale'] = $validated['preferred_locale'] ?? 'hy';

        $lead = $leads->create($validated);

        return (new LeadResource($lead->load(['service', 'product', 'package'])))->response()->setStatusCode(201);
    }

    public function show(Lead $lead): JsonResponse
    {
        return (new LeadResource($lead->load(['service', 'product', 'package'])))->response();
    }

    public function update(UpdateLeadRequest $request, Lead $lead): JsonResponse
    {
        $validated = $request->validated();

        if (array_key_exists('service_slug', $validated)) {
            $validated['service_id'] = $validated['service_slug']
                ? Service::query()->where('slug', $validated['service_slug'])->value('id')
                : null;
        }

        if (array_key_exists('product_slug', $validated)) {
            $validated['product_id'] = $validated['product_slug']
                ? Product::query()->where('slug', $validated['product_slug'])->value('id')
                : null;
        }

        if (array_key_exists('package_slug', $validated)) {
            $validated['package_id'] = $validated['package_slug']
                ? ServicePackage::query()->where('slug', $validated['package_slug'])->value('id')
                : null;
        }

        $lead->update(Arr::except($validated, ['service_slug', 'product_slug', 'package_slug']));

        return (new LeadResource($lead->fresh(['service', 'product', 'package'])))->response();
    }
}
