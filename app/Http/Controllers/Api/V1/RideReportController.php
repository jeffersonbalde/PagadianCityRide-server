<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Ride\ReportRide;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreRideReportRequest;
use App\Http\Resources\Api\V1\RideReportResource;
use App\Models\Ride;
use App\ReportCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class RideReportController extends Controller
{
    /**
     * Tell the admins what went wrong with the other person on the ride.
     */
    public function store(StoreRideReportRequest $request, Ride $ride, ReportRide $reportRide): JsonResponse
    {
        Gate::authorize('report', $ride);

        $report = $reportRide->handle(
            $ride,
            $request->user(),
            ReportCategory::from($request->validated('category')),
            $request->validated('details'),
        );

        return RideReportResource::make($report)->response()->setStatusCode(201);
    }
}
