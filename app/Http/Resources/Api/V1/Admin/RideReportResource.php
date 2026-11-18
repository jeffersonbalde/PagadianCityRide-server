<?php

namespace App\Http\Resources\Api\V1\Admin;

use App\Models\RideReport;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A report as the admins see it: who reported whom, on which ride, and what was done about it.
 *
 * @mixin RideReport
 */
class RideReportResource extends JsonResource
{
    /**
     * Transform the resource into an array. Load `ride`, `reporter` and `reportedUser` first.
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
            'admin_note' => $this->admin_note,
            'created_at' => $this->created_at->toIso8601String(),
            'handled_at' => $this->handled_at?->toIso8601String(),
            'reporter' => $this->person($this->reporter),
            'reported' => $this->person($this->reportedUser),
            'ride' => [
                'id' => $this->ride->id,
                'status' => $this->ride->status->value,
                'pickup_label' => $this->ride->pickup_label,
                'destination_label' => $this->ride->destination_label,
                'requested_at' => $this->ride->created_at->toIso8601String(),
            ],
        ];
    }

    /**
     * @return array{id: int, name: string|null, phone: string, role: string}
     */
    private function person(mixed $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'phone' => $user->phone,
            'role' => $user->role->value,
        ];
    }
}
