<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Actions\Driver\ApproveDriverApplication;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\DriverProfileResource;
use App\Models\DriverProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class DriverProfileApprovalController extends Controller
{
    /**
     * Approve an application that waits for review.
     */
    public function store(Request $request, DriverProfile $driverProfile, ApproveDriverApplication $approveDriverApplication): DriverProfileResource
    {
        Gate::authorize('review', $driverProfile);

        return DriverProfileResource::make(
            $approveDriverApplication->handle($driverProfile, $request->user())->load(['user', 'documents']),
        );
    }
}
