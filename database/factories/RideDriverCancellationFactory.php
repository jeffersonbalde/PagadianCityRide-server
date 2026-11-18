<?php

namespace Database\Factories;

use App\DriverCancelReason;
use App\Models\Ride;
use App\Models\RideDriverCancellation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RideDriverCancellation>
 */
class RideDriverCancellationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ride_id' => Ride::factory(),
            'driver_id' => User::factory()->driver(),
            'reason' => DriverCancelReason::TooFar,
            'note' => null,
        ];
    }
}
