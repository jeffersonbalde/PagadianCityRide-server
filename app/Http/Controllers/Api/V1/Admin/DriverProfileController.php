<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\DriverStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\DriverProfileResource;
use App\Models\DriverProfile;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class DriverProfileController extends Controller
{
    /**
     * List applications, oldest first. Only the ones that wait for review are
     * listed unless a status is given.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'status' => ['nullable', Rule::enum(DriverStatus::class)],
        ]);

        $driverProfiles = DriverProfile::query()
            ->with(['user', 'documents'])
            ->where('status', $validated['status'] ?? DriverStatus::Submitted->value)
            ->orderBy('submitted_at')
            ->orderBy('id')
            ->paginate(20);

        return DriverProfileResource::collection($driverProfiles);
    }

    /**
     * Show one application.
     */
    public function show(DriverProfile $driverProfile): DriverProfileResource
    {
        Gate::authorize('view', $driverProfile);

        return DriverProfileResource::make($driverProfile->load(['user', 'documents']));
    }
}
