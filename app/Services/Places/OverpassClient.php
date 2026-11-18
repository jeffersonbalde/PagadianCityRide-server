<?php

namespace App\Services\Places;

use Illuminate\Support\Facades\Http;

/**
 * Asks the Overpass API for the places of Pagadian City that are in OpenStreetMap.
 */
class OverpassClient
{
    private const AMENITIES = 'hospital|clinic|school|college|university|kindergarten|place_of_worship|marketplace|bus_station|ferry_terminal|townhall|police|courthouse|fire_station';

    /**
     * @return list<array{type: string, id: int, lat?: float, lon?: float, center?: array{lat: float, lon: float}, tags?: array<string, string>}>
     */
    public function places(): array
    {
        $response = Http::acceptJson()
            ->withUserAgent(config('places.user_agent'))
            ->asForm()
            ->connectTimeout(10)
            ->timeout(120)
            ->retry(3, 5000)
            ->post(config('places.overpass_url'), ['data' => $this->query()])
            ->throw();

        return $response->json('elements', []);
    }

    public function query(): string
    {
        $scope = $this->scope();
        $amenities = self::AMENITIES;

        return implode("\n", [
            '[out:json][timeout:90];',
            '(',
            "  nwr[\"shop\"~\"^(mall|supermarket)$\"]{$scope};",
            "  nwr[\"amenity\"~\"^({$amenities})$\"]{$scope};",
            "  nwr[\"aeroway\"=\"aerodrome\"]{$scope};",
            "  nwr[\"place\"~\"^(suburb|neighbourhood|village|quarter|hamlet)$\"]{$scope};",
            "  nwr[\"tourism\"~\"^(attraction|viewpoint|museum|hotel)$\"]{$scope};",
            ');',
            'out center tags;',
        ]);
    }

    /**
     * Where to search: inside the relation of the city, or inside the box around it.
     */
    private function scope(): string
    {
        if ($relation = config('places.relation_id')) {
            return '(area:'.(3600000000 + (int) $relation).')';
        }

        $bounds = config('places.bounds');

        return "({$bounds['south']},{$bounds['west']},{$bounds['north']},{$bounds['east']})";
    }
}
