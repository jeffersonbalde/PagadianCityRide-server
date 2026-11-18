<?php

namespace Database\Seeders;

use App\Models\Place;
use App\PlaceCategory;
use Illuminate\Database\Seeder;

class PlaceSeeder extends Seeder
{
    /**
     * Seed the main places a passenger can pick as a destination. The rest of Pagadian City
     * comes from `php artisan places:import`.
     *
     * The coordinates are approximate sample values around Pagadian City. Check
     * and correct them on the map before the app goes live.
     */
    public function run(): void
    {
        $places = [
            ['Airport', 'Tiguma', PlaceCategory::Terminal, 7.8283, 123.4597],
            ['Bus terminal', 'Balangasan', PlaceCategory::Terminal, 7.8231, 123.4402],
            ['Capitol', 'Balangasan', PlaceCategory::Government, 7.8268, 123.4411],
            ['Cathedral', 'Rizal Ave.', PlaceCategory::Church, 7.8256, 123.4372],
            ['City Hall', 'Balangasan', PlaceCategory::Government, 7.8249, 123.4389],
            ['City Plaza', 'Rizal Ave.', PlaceCategory::Other, 7.8252, 123.4366],
            ['Elementary school', 'Balangasan', PlaceCategory::School, 7.8238, 123.4421],
            ['High school', 'Santa Lucia', PlaceCategory::School, 7.8189, 123.4349],
            ['Hospital', 'Santa Lucia', PlaceCategory::Hospital, 7.8201, 123.4338],
            ['Medical Center', 'Balangasan', PlaceCategory::Hospital, 7.8274, 123.4435],
            ['Police station', 'Santa Lucia', PlaceCategory::Government, 7.8195, 123.4357],
            ['Port', 'Gatas', PlaceCategory::Terminal, 7.8326, 123.4311],
            ['Public market', 'Rizal Ave.', PlaceCategory::Market, 7.8259, 123.4358],
        ];

        foreach ($places as [$name, $area, $category, $latitude, $longitude]) {
            Place::updateOrCreate(
                ['name' => $name, 'area' => $area],
                ['category' => $category, 'source' => 'seed', 'latitude' => $latitude, 'longitude' => $longitude],
            );
        }
    }
}
