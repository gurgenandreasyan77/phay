<?php

namespace App\Services;

use App\Enums\LeadStatus;
use App\Models\Lead;
use App\Models\Product;
use App\Models\Service;
use App\Models\ServicePackage;
use Illuminate\Support\Arr;

class LeadService
{
    /**
     * @param  array<string, mixed>  $validated
     */
    public function create(array $validated): Lead
    {
        $serviceId = null;

        if (! empty($validated['service_slug'])) {
            $serviceId = Service::query()->where('slug', $validated['service_slug'])->value('id');
        }

        $productId = null;

        if (! empty($validated['product_slug'])) {
            $productId = Product::query()
                ->where('slug', $validated['product_slug'])
                ->orWhere('slug_hy', $validated['product_slug'])
                ->orWhere('slug_ru', $validated['product_slug'])
                ->orWhere('slug_en', $validated['product_slug'])
                ->value('id');
        }

        $packageId = null;

        if (! empty($validated['package_slug'])) {
            $packageId = ServicePackage::query()->where('slug', $validated['package_slug'])->value('id');
        }

        return Lead::create([
            ...Arr::except($validated, ['service_slug', 'product_slug', 'package_slug', 'consent']),
            'service_id' => $serviceId,
            'product_id' => $productId,
            'package_id' => $packageId,
            'consent' => true,
            'source' => $validated['source'] ?? 'website',
            'status' => LeadStatus::New,
        ]);
    }
}
