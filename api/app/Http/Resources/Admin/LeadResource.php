<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LeadResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'phone' => $this->phone,
            'email' => $this->email,
            'service' => $this->whenLoaded('service', fn () => $this->service ? [
                'id' => $this->service->id,
                'slug' => $this->service->slug,
                'name' => $this->service->trans('name', 'hy'),
            ] : null),
            'product' => $this->whenLoaded('product', fn () => $this->product ? [
                'id' => $this->product->id,
                'slug' => $this->product->slug,
                'name' => $this->product->trans('name', 'hy'),
            ] : null),
            'package' => $this->whenLoaded('package', fn () => $this->package ? [
                'id' => $this->package->id,
                'slug' => $this->package->slug,
                'name' => $this->package->trans('name', 'hy'),
            ] : null),
            'propertyType' => $this->property_type?->value,
            'areaSqm' => $this->area_sqm,
            'fulfillmentType' => $this->fulfillment_type?->value,
            'address' => $this->address,
            'preferredDate' => $this->preferred_date?->toDateString(),
            'comment' => $this->comment,
            'preferredLocale' => $this->preferred_locale?->value,
            'source' => $this->source,
            'status' => $this->status->value,

            'carBrand' => $this->car_brand,
            'carModel' => $this->car_model,
            'carYear' => $this->car_year,
            'carBodyType' => $this->car_body_type?->value,
            'seatsCount' => $this->seats_count,
            'interiorMaterial' => $this->interior_material?->value,
            'soilingLevel' => $this->soiling_level?->value,
            'contaminationTypes' => $this->contamination_types ?? [],
            'hasOdor' => $this->has_odor,
            'odorSource' => $this->odor_source,
            'hasPetHair' => $this->has_pet_hair,
            'hasChildSeat' => $this->has_child_seat,
            'canBringCar' => $this->can_bring_car,
            'preferredChannel' => $this->preferred_channel?->value,
            'photos' => $this->photos ?? [],
            'adminComment' => $this->admin_comment,
            'estimatedPriceMin' => $this->estimated_price_min,
            'estimatedPriceMax' => $this->estimated_price_max,

            'createdAt' => $this->created_at?->toIso8601String(),
            'updatedAt' => $this->updated_at?->toIso8601String(),
        ];
    }
}
