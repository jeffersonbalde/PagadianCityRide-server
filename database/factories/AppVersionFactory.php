<?php

namespace Database\Factories;

use App\AppPlatform;
use App\Models\AppVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AppVersion>
 */
class AppVersionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'platform' => AppPlatform::Android,
            'minimum_version' => '1.0.2',
        ];
    }
}
