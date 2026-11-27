<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\DriverProfile;
use App\Support\Distance;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NearbyDriverController extends Controller
{
    /**
     * The tricycles that are online, nearest to the passenger first. Only where
     * they are is shown, never who they are.
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'latitude' => ['required', 'numeric', 'between:4,22'],
            'longitude' => ['required', 'numeric', 'between:116,127'],
        ]);

        $radius = config('rides.search_radius_meters');

        $drivers = DriverProfile::query()
            ->availableNear((float) $validated['latitude'], (float) $validated['longitude'], $radius)
            ->get(['id', 'latitude', 'longitude'])
            ->map(fn (DriverProfile $profile): array => [
                'latitude' => $profile->latitude,
                'longitude' => $profile->longitude,
                'distance_meters' => Distance::meters((float) $validated['latitude'], (float) $validated['longitude'], $profile->latitude, $profile->longitude),
            ])
            ->filter(fn (array $driver): bool => $radius === null || $driver['distance_meters'] <= $radius)
            ->sortBy('distance_meters')
            ->values();

        return response()->json([
            'count' => $drivers->count(),
            'nearest_meters' => $drivers->first()['distance_meters'] ?? null,
            'drivers' => $drivers->map(fn (array $driver): array => [
                'latitude' => $driver['latitude'],
                'longitude' => $driver['longitude'],
            ])->all(),
        ]);
    }
}
