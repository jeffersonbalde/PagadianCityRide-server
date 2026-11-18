<?php

namespace App\Http\Resources\Api\V1;

use App\Models\RideReport;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A report as the person who made it sees it. The notes of the admins are not here.
 *
 * @mixin RideReport
 */
class RideReportResource extends JsonResource
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
            'category' => $this->category->value,
            'details' => $this->details,
            'status' => $this->status->value,
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
