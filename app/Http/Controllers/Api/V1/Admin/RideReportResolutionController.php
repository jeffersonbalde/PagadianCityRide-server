<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Actions\Ride\ResolveRideReport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\StoreRideReportResolutionRequest;
use App\Http\Resources\Api\V1\Admin\RideReportResource;
use App\Models\RideReport;

class RideReportResolutionController extends Controller
{
    /**
     * Close an open report with a note.
     */
    public function store(StoreRideReportResolutionRequest $request, RideReport $rideReport, ResolveRideReport $resolveRideReport): RideReportResource
    {
        $report = $resolveRideReport->handle($rideReport, $request->user(), $request->status(), $request->validated('note'));

        return RideReportResource::make($report->load(['ride', 'reporter', 'reportedUser']));
    }
}
