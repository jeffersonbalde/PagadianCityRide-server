<?php

namespace App\Http\Resources\Api\V1;

use App\DocumentStatus;
use App\DocumentType;
use App\Models\DriverProfile;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Load `user` and `documents` before using this resource.
 *
 * @mixin DriverProfile
 */
class DriverProfileResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $missing = $this->missingDocumentTypes();
        $hasRejectedDocument = $this->documents->contains(fn ($document): bool => $document->status === DocumentStatus::Rejected);

        return [
            'id' => $this->id,
            'status' => $this->status->value,
            'name' => $this->user->name,
            'phone' => $this->user->phone,
            'tricycle_number' => $this->tricycle_number,
            'plate_number' => $this->plate_number,
            'franchise_number' => $this->franchise_number,
            'is_online' => (bool) $this->is_online,
            'rejection_reason' => $this->rejection_reason,
            'submitted_at' => $this->submitted_at?->toIso8601String(),
            'reviewed_at' => $this->reviewed_at?->toIso8601String(),
            'documents' => DriverDocumentResource::collection($this->documents),
            'missing_documents' => array_map(fn (DocumentType $type): string => $type->value, $missing),
            'can_submit' => $this->status->isEditable()
                && filled($this->user->name)
                && $missing === []
                && ! $hasRejectedDocument,
        ];
    }
}
