<?php

namespace App\Http\Controllers\Api\V1\Driver;

use App\DriverStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Driver\UpdateDriverProfileRequest;
use App\Http\Resources\Api\V1\DriverProfileResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class DriverProfileController extends Controller
{
    /**
     * Show the application of the signed-in driver.
     */
    public function show(Request $request): DriverProfileResource
    {
        $driverProfile = $request->user()->driverProfile;

        abort_if($driverProfile === null, 404, 'You have not started the application yet.');

        return DriverProfileResource::make($driverProfile->load(['user', 'documents']));
    }

    /**
     * Start the application, or change the details while it can still be changed.
     */
    public function update(UpdateDriverProfileRequest $request): JsonResponse
    {
        $user = $request->user();

        if ($user->driverProfile !== null) {
            Gate::authorize('update', $user->driverProfile);
        }

        $driverProfile = $user->driverProfile
            ?? $user->driverProfile()->make(['status' => DriverStatus::Draft]);

        $isNew = ! $driverProfile->exists;

        DB::transaction(function () use ($request, $user, $driverProfile): void {
            $user->update(['name' => $request->validated('name')]);

            $user->driverProfile()->save(
                $driverProfile->fill($request->safe()->only(['tricycle_number', 'plate_number', 'franchise_number'])),
            );
        });

        return DriverProfileResource::make($driverProfile->load(['user', 'documents']))
            ->response()
            ->setStatusCode($isNew ? 201 : 200);
    }
}
