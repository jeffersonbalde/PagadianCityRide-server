<?php

namespace Database\Factories;

use App\Models\EmailLoginCode;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmailLoginCode>
 */
class EmailLoginCodeFactory extends Factory
{
    /**
     * Define the model's default state. The code is hashed by the model cast.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'email' => fake()->unique()->safeEmail(),
            'code' => '123456',
            'attempts' => 0,
            'expires_at' => now()->addMinutes(10),
            'consumed_at' => null,
        ];
    }

    /**
     * Indicate that the code can no longer be used because it expired.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'expires_at' => now()->subMinute(),
        ]);
    }

    /**
     * Indicate that the code was already used to sign in.
     */
    public function consumed(): static
    {
        return $this->state(fn (array $attributes) => [
            'consumed_at' => now(),
        ]);
    }

    /**
     * Indicate that the code was entered wrongly too many times.
     */
    public function locked(): static
    {
        return $this->state(fn (array $attributes) => [
            'attempts' => EmailLoginCode::MAX_ATTEMPTS,
        ]);
    }
}
