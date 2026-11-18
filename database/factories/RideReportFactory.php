<?php

namespace Database\Factories;

use App\Models\Ride;
use App\Models\RideReport;
use App\ReportCategory;
use App\ReportStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RideReport>
 */
class RideReportFactory extends Factory
{
    /**
     * Define the model's default state: an open report by the passenger of a completed ride
     * about the driver of that ride.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $ride = Ride::factory()->completed()->create();

        return [
            'ride_id' => $ride->id,
            'reporter_id' => $ride->passenger_id,
            'reported_user_id' => $ride->driver_id,
            'category' => ReportCategory::Safety,
            'details' => fake()->sentence(),
            'status' => ReportStatus::Open,
        ];
    }

    /**
     * Indicate that an admin handled the report.
     */
    public function resolved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ReportStatus::Resolved,
            'admin_note' => 'We talked to the driver.',
            'handled_at' => now(),
        ]);
    }

    /**
     * Indicate that an admin found nothing to act on.
     */
    public function dismissed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ReportStatus::Dismissed,
            'admin_note' => 'Nothing to act on.',
            'handled_at' => now(),
        ]);
    }
}
