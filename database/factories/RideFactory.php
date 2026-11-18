<?php

namespace Database\Factories;

use App\Models\Ride;
use App\Models\User;
use App\RideStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Ride>
 */
class RideFactory extends Factory
{
    /**
     * Define the model's default state: a request that waits for a driver. The
     * pickup is at the Balangasan area and the destination at the City Hall.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'passenger_id' => User::factory(),
            'driver_id' => null,
            'status' => RideStatus::Requested,
            'pickup_latitude' => 7.8231,
            'pickup_longitude' => 123.4402,
            'pickup_label' => 'Balangasan',
            'destination_latitude' => 7.8249,
            'destination_longitude' => 123.4389,
            'destination_label' => 'City Hall',
            'passengers_count' => 1,
            'note' => null,
            'distance_meters' => 1800,
            'duration_seconds' => 420,
        ];
    }

    /**
     * Pick up from another point.
     */
    public function pickupAt(float $latitude, float $longitude): static
    {
        return $this->state(fn (array $attributes) => [
            'pickup_latitude' => $latitude,
            'pickup_longitude' => $longitude,
        ]);
    }

    /**
     * Indicate that a driver picked the request up.
     */
    public function accepted(?User $driver = null): static
    {
        return $this->state(fn (array $attributes) => [
            'driver_id' => $driver?->id ?? User::factory()->driver(),
            'status' => RideStatus::Accepted,
            'accepted_at' => now(),
        ]);
    }

    /**
     * Indicate that the driver is at the pickup point.
     */
    public function arrived(?User $driver = null): static
    {
        return $this->accepted($driver)->state(fn (array $attributes) => [
            'status' => RideStatus::Arrived,
            'arrived_at' => now(),
        ]);
    }

    /**
     * Indicate that the passenger is on the tricycle.
     */
    public function inProgress(?User $driver = null): static
    {
        return $this->arrived($driver)->state(fn (array $attributes) => [
            'status' => RideStatus::InProgress,
            'started_at' => now(),
        ]);
    }

    /**
     * Indicate that the trip is done.
     */
    public function completed(?User $driver = null): static
    {
        return $this->inProgress($driver)->state(fn (array $attributes) => [
            'status' => RideStatus::Completed,
            'completed_at' => now(),
        ]);
    }

    /**
     * Indicate that the passenger cancelled the request.
     */
    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => RideStatus::Cancelled,
            'cancelled_at' => now(),
        ]);
    }

    /**
     * Indicate that no driver picked the request up in time.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => RideStatus::Expired,
        ]);
    }

    /**
     * Indicate that the passenger rated the driver.
     */
    public function rated(int $stars = 5, ?string $review = null): static
    {
        return $this->state(fn (array $attributes) => [
            'rating' => $stars,
            'review' => $review,
            'rated_at' => now(),
        ]);
    }
}
