<?php

namespace App\Support;

class Distance
{
    private const EARTH_RADIUS_METERS = 6371000;

    /**
     * The straight-line distance between two points on the map, in meters.
     */
    public static function meters(float $fromLatitude, float $fromLongitude, float $toLatitude, float $toLongitude): int
    {
        $latitudeDelta = deg2rad($toLatitude - $fromLatitude);
        $longitudeDelta = deg2rad($toLongitude - $fromLongitude);

        $a = sin($latitudeDelta / 2) ** 2
            + cos(deg2rad($fromLatitude)) * cos(deg2rad($toLatitude)) * sin($longitudeDelta / 2) ** 2;

        return (int) round(self::EARTH_RADIUS_METERS * 2 * atan2(sqrt($a), sqrt(1 - $a)));
    }

    /**
     * The corners of a square around a point, a little bigger than the circle
     * of the given radius. Use it to narrow a database query before the exact
     * distance is measured.
     *
     * @return array{minLatitude: float, maxLatitude: float, minLongitude: float, maxLongitude: float}
     */
    public static function boundingBox(float $latitude, float $longitude, int $radiusMeters): array
    {
        $latitudeDelta = rad2deg($radiusMeters / self::EARTH_RADIUS_METERS);
        $longitudeDelta = rad2deg($radiusMeters / (self::EARTH_RADIUS_METERS * max(cos(deg2rad($latitude)), 0.01)));

        return [
            'minLatitude' => $latitude - $latitudeDelta,
            'maxLatitude' => $latitude + $latitudeDelta,
            'minLongitude' => $longitude - $longitudeDelta,
            'maxLongitude' => $longitude + $longitudeDelta,
        ];
    }
}
