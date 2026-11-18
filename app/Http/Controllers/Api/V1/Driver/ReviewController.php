<?php

namespace App\Http\Controllers\Api\V1\Driver;

use App\Http\Controllers\Controller;
use App\Models\Ride;
use App\RideStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ReviewController extends Controller
{
    /**
     * The rating of the driver, the trips finished and what passengers wrote, newest first. A passenger can give
     * stars without words, so a review can have no text.
     */
    public function index(Request $request): JsonResponse
    {
        $driver = $request->user();

        $rated = $driver->ridesAsDriver()->where('status', RideStatus::Completed)->whereNotNull('rating');

        $reviews = (clone $rated)
            ->with('passenger')
            ->latest('completed_at')
            ->latest('id')
            ->limit(30)
            ->get();

        $average = (clone $rated)->avg('rating');

        return response()->json([
            'rating_average' => $average === null ? null : round((float) $average, 1),
            'completed_trips_count' => $driver->ridesAsDriver()->where('status', RideStatus::Completed)->count(),
            'reviews' => $reviews->map(fn (Ride $review): array => [
                'ride_id' => $review->id,
                'rating' => $review->rating,
                'review' => $review->review,
                'reviewer' => Str::of($review->passenger->name ?? '')->before(' ')->toString(),
                'completed_at' => $review->completed_at?->toIso8601String(),
            ])->all(),
        ]);
    }
}
