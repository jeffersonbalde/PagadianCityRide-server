<?php

namespace App\Services\Routing;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Finds the way by road between two points, to draw on the map: the points of the line, how long it is and how
 * long it takes by car. A route that was found is kept, because the same trip is asked for again and again.
 */
class RoadRouteFinder
{
    /**
     * @return array{distance_meters: int, duration_seconds: int, points: list<array{0: float, 1: float}>}|null null when no route could be found
     */
    public function find(float $fromLatitude, float $fromLongitude, float $toLatitude, float $toLongitude): ?array
    {
        $key = sprintf('road-route:%.5f,%.5f:%.5f,%.5f', $fromLatitude, $fromLongitude, $toLatitude, $toLongitude);

        $cached = Cache::get($key);

        if ($cached !== null) {
            return $cached;
        }

        $route = $this->ask($fromLatitude, $fromLongitude, $toLatitude, $toLongitude);

        if ($route !== null) {
            Cache::put($key, $route, now()->addHours(config('routing.cache_hours')));
        }

        return $route;
    }

    /**
     * @return array{distance_meters: int, duration_seconds: int, points: list<array{0: float, 1: float}>}|null
     */
    private function ask(float $fromLatitude, float $fromLongitude, float $toLatitude, float $toLongitude): ?array
    {
        $url = rtrim(config('routing.osrm_url'), '/')."/route/v1/driving/{$fromLongitude},{$fromLatitude};{$toLongitude},{$toLatitude}";

        try {
            $response = Http::acceptJson()
                ->withUserAgent(config('routing.user_agent'))
                ->connectTimeout(3)
                ->timeout(6)
                ->retry(2, 300, throw: false)
                ->get($url, ['overview' => 'full', 'geometries' => 'geojson']);
        } catch (ConnectionException) {
            return null;
        }

        $route = $response->successful() ? $response->json('routes.0') : null;
        $coordinates = $route['geometry']['coordinates'] ?? [];

        if (count($coordinates) < 2) {
            return null;
        }

        return [
            'distance_meters' => (int) round($route['distance']),
            'duration_seconds' => (int) round($route['duration']),
            'points' => $this->points($coordinates),
        ];
    }

    /**
     * OSRM gives [longitude, latitude]. The app wants [latitude, longitude], to 5 decimals (about a meter), without
     * the points that repeat.
     *
     * @param  list<array{0: float, 1: float}>  $coordinates
     * @return list<array{0: float, 1: float}>
     */
    private function points(array $coordinates): array
    {
        $points = [];

        foreach ($coordinates as [$longitude, $latitude]) {
            $point = [round($latitude, 5), round($longitude, 5)];

            if ($point !== end($points)) {
                $points[] = $point;
            }
        }

        return $points;
    }
}
