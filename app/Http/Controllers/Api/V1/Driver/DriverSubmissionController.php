<?php

namespace App\Http\Controllers\Api\V1\Driver;

use App\Actions\Driver\SubmitDriverApplication;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\DriverProfileResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class DriverSubmissionController extends Controller
{
    /**
     * Send the application for review.
     */
    public function store(Request $request, SubmitDriverApplication $submitDriverApplication): DriverProfileResource
    {
        $driverProfile = $request->user()->driverProfile;

        abort_if($driverProfile === null, 404, 'You have not started the application yet.');

        Gate::authorize('update', $driverProfile);

        return DriverProfileResource::make(
            $submitDriverApplication->handle($driverProfile)->load(['user', 'documents']),
        );
    }
}
