<?php

namespace Database\Factories;

use App\Models\EmergencyContact;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmergencyContact>
 */
class EmergencyContactFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->firstName(),
            'relationship' => fake()->randomElement(['Sister', 'Brother', 'Mother', 'Friend']),
            'phone' => '+6391'.fake()->unique()->numerify('########'),
        ];
    }
}
