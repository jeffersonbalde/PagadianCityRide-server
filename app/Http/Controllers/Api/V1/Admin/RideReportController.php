<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Admin\RideReportResource;
use App\Models\RideReport;
use App\ReportStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class RideReportController extends Controller
{
    /**
     * The reports of one status, the open ones by default, oldest first so none waits forever.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'status' => ['nullable', Rule::enum(ReportStatus::class)],
        ]);

        $reports = RideReport::query()
            ->with(['ride', 'reporter', 'reportedUser'])
            ->where('status', $validated['status'] ?? ReportStatus::Open->value)
            ->orderBy('created_at')
            ->orderBy('id')
            ->paginate(20);

        return RideReportResource::collection($reports);
    }

    public function show(RideReport $rideReport): RideReportResource
    {
        return RideReportResource::make($rideReport->load(['ride', 'reporter', 'reportedUser']));
    }
}
