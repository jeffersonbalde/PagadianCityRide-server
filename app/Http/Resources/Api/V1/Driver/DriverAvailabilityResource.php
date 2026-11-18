<?php

namespace App\Http\Resources\Api\V1\Driver;

use App\Models\DriverProfile;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin DriverProfile
 */
class DriverAvailabilityResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'is_online' => $this->is_online,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'located_at' => $this->located_at?->toIso8601String(),
        ];
    }
}
