<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\DriverStatus;
use App\Http\Controllers\Controller;
use App\Models\DriverProfile;
use App\Models\Ride;
use App\Models\RideReport;
use App\ReportStatus;
use App\RideStatus;
use App\TripRange;
use Illuminate\Http\JsonResponse;

class OverviewController extends Controller
{
    /**
     * The numbers of the day for the admins: who is online, who waits, the trips of today, how long a
     * passenger waits on average, what waits for the admins, and the last trips.
     */
    public function show(): JsonResponse
    {
        $acceptedToday = Ride::query()
            ->inRange(TripRange::Today)
            ->whereNotNull('accepted_at')
            ->get(['created_at', 'accepted_at']);

        $averageWaitSeconds = $acceptedToday->isEmpty()
            ? null
            : $acceptedToday->avg(fn (Ride $ride): int => $ride->created_at->diffInSeconds($ride->accepted_at, true));

        $recent = Ride::query()
            ->with(['driver.driverProfile'])
            ->latest()
            ->latest('id')
            ->limit(10)
            ->get();

        return response()->json([
            'drivers_online' => DriverProfile::query()
                ->where('status', DriverStatus::Approved)
                ->where('is_online', true)
                ->where('located_at', '>=', now()->subMinutes(config('rides.driver_location_stale_after_minutes')))
                ->count(),
            'passengers_waiting' => Ride::query()->where('status', RideStatus::Requested)->count(),
            'trips_today' => Ride::query()->inRange(TripRange::Today)->where('status', RideStatus::Completed)->count(),
            'average_wait_minutes' => $averageWaitSeconds === null ? null : round($averageWaitSeconds / 60, 1),
            'pending_applications' => DriverProfile::query()->where('status', DriverStatus::Submitted)->count(),
            'open_reports' => RideReport::query()->where('status', ReportStatus::Open)->count(),
            'recent_trips' => $recent->map(fn (Ride $ride): array => [
                'id' => $ride->id,
                'requested_at' => $ride->created_at->toIso8601String(),
                'pickup_label' => $ride->pickup_label,
                'destination_label' => $ride->destination_label,
                'driver_name' => $ride->driver?->name,
                'tricycle_number' => $ride->driver?->driverProfile?->tricycle_number,
                'wait_minutes' => $ride->accepted_at === null ? null : round($ride->created_at->diffInSeconds($ride->accepted_at, true) / 60, 1),
                'status' => $ride->status->value,
            ])->all(),
        ]);
    }
}
