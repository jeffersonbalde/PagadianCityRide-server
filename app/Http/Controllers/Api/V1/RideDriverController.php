<?php

namespace App\Http\Controllers\Api\V1;

use App\DocumentStatus;
use App\DocumentType;
use App\DriverStatus;
use App\Http\Controllers\Controller;
use App\Models\Ride;
use App\Models\User;
use App\RideStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RideDriverController extends Controller
{
    /**
     * The photo of the driver, from the registration. The route is signed: only the link that the passenger of the
     * ride was given works.
     */
    public function photo(Ride $ride): StreamedResponse
    {
        $document = $ride->driver?->driverProfile?->documents()->where('type', DocumentType::Photo)->first();

        abort_if($document === null, 404);

        return Storage::response($document->path, null, ['Cache-Control' => 'private, max-age=3600']);
    }

    /**
     * The profile of the driver of a ride, for the passenger: who is coming, what PagadianCityRide verified,
     * what other passengers said, and the contact number, as the two shared a ride.
     */
    public function show(Ride $ride): JsonResponse
    {
        Gate::authorize('viewDriver', $ride);

        $driver = User::query()
            ->with(['driverProfile.documents'])
            ->withAvg('ridesAsDriver as rating_average', 'rating')
            ->withCount(['ridesAsDriver as completed_trips_count' => fn ($rides) => $rides->where('status', RideStatus::Completed)])
            ->findOrFail($ride->driver_id);

        $profile = $driver->driverProfile;

        $reviews = $driver->ridesAsDriver()
            ->with('passenger')
            ->whereNotNull('rating')
            ->whereNotNull('review')
            ->latest('completed_at')
            ->limit(10)
            ->get();

        return response()->json([
            'name' => $driver->name,
            'phone' => $driver->phone,
            'trips_together' => Ride::query()->where('passenger_id', $ride->passenger_id)->where('driver_id', $driver->id)->where('status', RideStatus::Completed)->count(),
            'photo_url' => $profile?->documents->contains('type', DocumentType::Photo)
                ? $ride->setRelation('driver', $driver)->driverPhotoUrl()
                : null,
            'tricycle_number' => $profile?->tricycle_number,
            'plate_number' => $profile?->plate_number,
            'franchise_number' => $profile?->franchise_number,
            'rating_average' => $driver->rating_average === null ? null : round((float) $driver->rating_average, 1),
            'completed_trips_count' => (int) $driver->completed_trips_count,
            'verified' => $profile?->status === DriverStatus::Approved,
            'documents' => collect(DocumentType::cases())->map(fn (DocumentType $type): array => [
                'type' => $type->value,
                'verified' => $profile?->documents->firstWhere('type', $type)?->status === DocumentStatus::Verified,
            ])->all(),
            'reviews' => $reviews->map(fn (Ride $review): array => [
                'rating' => $review->rating,
                'review' => $review->review,
                'reviewer' => Str::of($review->passenger->name ?? '')->before(' ')->toString(),
                'completed_at' => $review->completed_at?->toIso8601String(),
            ])->all(),
        ]);
    }
}
