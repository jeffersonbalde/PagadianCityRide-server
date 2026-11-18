<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\DriverStatus;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\RideStatus;
use Illuminate\Http\JsonResponse;

class DriverController extends Controller
{
    /**
     * The approved drivers, by name, with whether they are online now, their rating and their finished trips.
     */
    public function index(): JsonResponse
    {
        $drivers = User::query()
            ->whereHas('driverProfile', fn ($profile) => $profile->where('status', DriverStatus::Approved))
            ->with('driverProfile')
            ->withAvg('ridesAsDriver as rating_average', 'rating')
            ->withCount(['ridesAsDriver as completed_trips_count' => fn ($rides) => $rides->where('status', RideStatus::Completed)])
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(30);

        $freshAfter = now()->subMinutes(config('rides.driver_location_stale_after_minutes'));

        return response()->json([
            'data' => $drivers->getCollection()->map(fn (User $driver): array => [
                'id' => $driver->id,
                'name' => $driver->name,
                'phone' => $driver->phone,
                'tricycle_number' => $driver->driverProfile->tricycle_number,
                'plate_number' => $driver->driverProfile->plate_number,
                'is_online' => $driver->driverProfile->is_online && $driver->driverProfile->located_at?->greaterThanOrEqualTo($freshAfter) === true,
                'rating_average' => $driver->rating_average === null ? null : round((float) $driver->rating_average, 1),
                'completed_trips_count' => (int) $driver->completed_trips_count,
            ])->all(),
            'meta' => [
                'current_page' => $drivers->currentPage(),
                'last_page' => $drivers->lastPage(),
                'total' => $drivers->total(),
            ],
        ]);
    }
}
