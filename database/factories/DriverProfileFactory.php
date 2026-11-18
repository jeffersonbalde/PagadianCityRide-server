<?php

namespace Database\Factories;

use App\DocumentStatus;
use App\DocumentType;
use App\DriverStatus;
use App\Models\DriverDocument;
use App\Models\DriverProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DriverProfile>
 */
class DriverProfileFactory extends Factory
{
    /**
     * Define the model's default state: a draft with no documents yet.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->driver(),
            'tricycle_number' => '#'.fake()->unique()->numerify('###'),
            'plate_number' => strtoupper(fake()->unique()->bothify('???? ####')),
            'franchise_number' => fake()->unique()->numerify('####-####'),
            'status' => DriverStatus::Draft,
        ];
    }

    /**
     * Add one uploaded document of every type.
     */
    public function withAllDocuments(DocumentStatus $status = DocumentStatus::Pending): static
    {
        return $this->afterCreating(function (DriverProfile $profile) use ($status): void {
            foreach (DocumentType::cases() as $type) {
                DriverDocument::factory()
                    ->for($profile)
                    ->state(['type' => $type, 'status' => $status])
                    ->create();
            }
        });
    }

    /**
     * Indicate that the driver is approved, online and at the given point.
     */
    public function onlineAt(float $latitude = 7.8231, float $longitude = 123.4402): static
    {
        return $this->approved()->state(fn (array $attributes) => [
            'is_online' => true,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'located_at' => now(),
        ]);
    }

    /**
     * Indicate that the application was sent and waits for review.
     */
    public function submitted(): static
    {
        return $this->withAllDocuments()->state(fn (array $attributes) => [
            'status' => DriverStatus::Submitted,
            'submitted_at' => now(),
        ]);
    }

    /**
     * Indicate that an admin approved the application.
     */
    public function approved(): static
    {
        return $this->withAllDocuments(DocumentStatus::Verified)->state(fn (array $attributes) => [
            'status' => DriverStatus::Approved,
            'submitted_at' => now()->subDay(),
            'reviewed_at' => now(),
        ]);
    }

    /**
     * Indicate that an admin sent the application back.
     */
    public function rejected(string $reason = 'The photos are not clear.'): static
    {
        return $this->withAllDocuments()->state(fn (array $attributes) => [
            'status' => DriverStatus::Rejected,
            'rejection_reason' => $reason,
            'submitted_at' => now()->subDay(),
            'reviewed_at' => now(),
        ]);
    }
}
