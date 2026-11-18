<?php

namespace App\Http\Resources\Api\V1;

use App\Models\RideMessage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One chat message of a ride.
 *
 * @mixin RideMessage
 */
class RideMessageResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'ride_id' => $this->ride_id,
            'sender_id' => $this->sender_id,
            'body' => $this->body,
            'image_url' => $this->imageUrl(),
            'read_at' => $this->read_at?->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
