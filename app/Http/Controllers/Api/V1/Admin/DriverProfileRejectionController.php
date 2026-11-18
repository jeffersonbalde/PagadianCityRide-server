<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Actions\Driver\RejectDriverApplication;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\StoreDriverProfileRejectionRequest;
use App\Http\Resources\Api\V1\DriverProfileResource;
use App\Models\DriverProfile;
use Illuminate\Support\Facades\Gate;

class DriverProfileRejectionController extends Controller
{
    /**
     * Send an application back to the driver with a reason.
     */
    public function store(
        StoreDriverProfileRejectionRequest $request,
        DriverProfile $driverProfile,
        RejectDriverApplication $rejectDriverApplication,
    ): DriverProfileResource {
        Gate::authorize('review', $driverProfile);

        return DriverProfileResource::make(
            $rejectDriverApplication
                ->handle($driverProfile, $request->user(), $request->validated('reason'), $request->documentTypes())
                ->load(['user', 'documents']),
        );
    }
}
