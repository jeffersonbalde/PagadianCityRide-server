<?php

namespace App\Http\Resources\Api\V1;

use App\Models\User;
use App\UserRole;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Load `driverProfile` before using this resource for a driver.
 *
 * @mixin User
 */
class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array. A driver also gets the status of
     * the application, which is null until the driver starts it.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'phone' => $this->phone,
            'role' => $this->role->value,
            'driver_status' => $this->when(
                $this->role === UserRole::Driver,
                fn (): ?string => $this->driverProfile?->status->value,
            ),
        ];
    }
}
