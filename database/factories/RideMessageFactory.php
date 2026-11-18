<?php

namespace Database\Factories;

use App\Models\Ride;
use App\Models\RideMessage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RideMessage>
 */
class RideMessageFactory extends Factory
{
    /**
     * Define the model's default state: a message from the passenger of an accepted ride.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $ride = Ride::factory()->accepted();

        return [
            'ride_id' => $ride,
            'sender_id' => fn (array $attributes) => Ride::findOrFail($attributes['ride_id'])->passenger_id,
            'body' => fake()->sentence(4),
        ];
    }

    /**
     * Indicate that the other person read the message.
     */
    public function read(): static
    {
        return $this->state(fn (array $attributes) => ['read_at' => now()]);
    }
}
