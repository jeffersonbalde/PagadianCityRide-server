<?php

namespace Database\Factories;

use App\Models\Place;
use App\PlaceCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Place>
 */
class PlaceFactory extends Factory
{
    /**
     * Define the model's default state: a point near the center of Pagadian City.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'area' => fake()->randomElement(['Balangasan', 'Gatas', 'Santa Lucia', 'Tiguma', 'Rizal Ave.']),
            'latitude' => fake()->randomFloat(7, 7.81, 7.84),
            'longitude' => fake()->randomFloat(7, 123.42, 123.45),
            'category' => PlaceCategory::Other,
            'source' => 'manual',
        ];
    }
}
