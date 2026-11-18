<?php

namespace App\Http\Resources\Api\V1;

use App\Models\DriverDocument;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin DriverDocument
 */
class DriverDocumentResource extends JsonResource
{
    /**
     * Transform the resource into an array. The file path is never exposed, the
     * photo is read through its own route.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'type' => $this->type->value,
            'status' => $this->status->value,
            'rejection_reason' => $this->rejection_reason,
            'uploaded_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
