<?php

namespace Database\Factories;

use App\Models\User;
use App\UserRole;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'phone' => '+639'.fake()->unique()->numerify('#########'),
            'role' => UserRole::Passenger,
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'phone_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the person is a tricycle driver.
     */
    public function driver(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => UserRole::Driver,
        ]);
    }

    /**
     * Indicate that the person is an admin.
     */
    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => UserRole::Admin,
        ]);
    }

    /**
     * Indicate that an admin blocked the person.
     */
    public function blocked(string $reason = 'Rude to passengers.'): static
    {
        return $this->state(fn (array $attributes) => [
            'blocked_at' => now(),
            'blocked_reason' => $reason,
        ]);
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Indicate that the person signed up with a phone number only.
     */
    public function phoneOnly(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => null,
            'email' => null,
            'email_verified_at' => null,
            'password' => null,
        ]);
    }
}
