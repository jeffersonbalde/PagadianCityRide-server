<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Ride;
use App\RideStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Load `passenger` and `driver.driverProfile` before using this resource. The
 * driver also needs `rating_average` and `completed_trips_count` when the
 * passenger should see them, see Ride::loadForDisplay().
 *
 * What the person sees depends on who they are: the passenger sees the driver,
 * and the driver sees the passenger, including the phone number once the
 * driver took the ride.
 *
 * @mixin Ride
 */
class RideResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $viewerIsPassenger = $request->user()?->id === $this->passenger_id;
        $viewerIsDriver = $this->driver_id !== null && $request->user()?->id === $this->driver_id;

        return [
            'id' => $this->id,
            'status' => $this->status->value,
            'pickup' => [
                'latitude' => $this->pickup_latitude,
                'longitude' => $this->pickup_longitude,
                'label' => $this->pickup_label,
            ],
            'destination' => [
                'latitude' => $this->destination_latitude,
                'longitude' => $this->destination_longitude,
                'label' => $this->destination_label,
            ],
            'passengers_count' => $this->passengers_count,
            'note' => $this->note,
            'distance_meters' => $this->distance_meters,
            'duration_seconds' => $this->duration_seconds,
            'requested_at' => $this->created_at->toIso8601String(),
            'accepted_at' => $this->accepted_at?->toIso8601String(),
            'arrived_at' => $this->arrived_at?->toIso8601String(),
            'started_at' => $this->started_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'cancel_reason' => $this->cancel_reason,
            'rating' => $this->rating,
            'review' => $this->review,
            // The chat messages the viewer did not read yet, for the badge on the Message button. Only while the chat is open.
            'unread_messages' => $this->when(
                $this->driver_id !== null && in_array($this->status, [RideStatus::Accepted, RideStatus::Arrived, RideStatus::InProgress], true),
                fn (): int => $this->messages()->where('sender_id', '!=', $request->user()?->id)->whereNull('read_at')->count(),
            ),
            'my_report' => $this->whenLoaded('reports', fn (): ?RideReportResource => $this->reports->isEmpty()
                ? null
                : RideReportResource::make($this->reports->first())),
            'passenger' => $this->when($viewerIsDriver, fn (): array => [
                'name' => $this->passenger->name,
                'phone' => $this->passenger->phone,
            ]),
            'driver' => $this->when($viewerIsPassenger && $this->driver_id !== null, fn (): array => $this->driverBlock()),
            // While the request waits again because a driver gave it up, the passenger is told why on the home page.
            'driver_cancellation' => $this->when($viewerIsPassenger && $this->status === RideStatus::Requested, fn (): ?array => $this->lastDriverCancellation()),
        ];
    }

    /**
     * Why the driver who had this ride gave it up, for the passenger, or null when no driver did.
     *
     * @return array{reason: string, cancelled_at: string}|null
     */
    private function lastDriverCancellation(): ?array
    {
        $cancellation = $this->driverCancellations()->latest('id')->first();

        return $cancellation === null ? null : [
            'reason' => $cancellation->reason->words($cancellation->note),
            'cancelled_at' => $cancellation->created_at->toIso8601String(),
        ];
    }

    /**
     * What a passenger may know about the driver: how to call and recognize
     * them, and where the tricycle is while the ride goes on.
     *
     * @return array<string, mixed>
     */
    private function driverBlock(): array
    {
        $driver = $this->driver;
        $profile = $driver->driverProfile;

        return [
            'name' => $driver->name,
            'phone' => $driver->phone,
            'photo_url' => $this->driverPhotoUrl(),
            'tricycle_number' => $profile?->tricycle_number,
            'plate_number' => $profile?->plate_number,
            'rating_average' => isset($driver->rating_average) ? round((float) $driver->rating_average, 1) : null,
            'completed_trips_count' => $driver->completed_trips_count ?? null,
            'location' => $this->when($this->status->isActive() && $profile?->latitude !== null, fn (): array => [
                'latitude' => $profile->latitude,
                'longitude' => $profile->longitude,
                'located_at' => $profile->located_at?->toIso8601String(),
            ]),
        ];
    }
}
