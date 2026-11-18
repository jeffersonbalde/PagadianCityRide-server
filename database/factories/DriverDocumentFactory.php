<?php

namespace Database\Factories;

use App\DocumentStatus;
use App\DocumentType;
use App\Models\DriverDocument;
use App\Models\DriverProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DriverDocument>
 */
class DriverDocumentFactory extends Factory
{
    /**
     * Define the model's default state. The path points to a file that does not exist.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'driver_profile_id' => DriverProfile::factory(),
            'type' => fake()->randomElement(DocumentType::cases()),
            'path' => 'driver-documents/'.fake()->uuid().'.jpg',
            'status' => DocumentStatus::Pending,
            'rejection_reason' => null,
        ];
    }

    /**
     * Indicate that an admin checked the document.
     */
    public function verified(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => DocumentStatus::Verified,
        ]);
    }

    /**
     * Indicate that an admin refused the document.
     */
    public function rejected(string $reason = 'The text is not readable.'): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => DocumentStatus::Rejected,
            'rejection_reason' => $reason,
        ]);
    }
}
