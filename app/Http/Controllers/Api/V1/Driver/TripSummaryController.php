<?php

namespace App\Http\Controllers\Api\V1\Driver;

use App\Http\Controllers\Controller;
use App\RideStatus;
use App\TripRange;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TripSummaryController extends Controller
{
    /**
     * How many trips the driver finished and how many passengers rode, today,
     * this week or in total. The default is the total.
     */
    public function show(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'range' => ['nullable', Rule::enum(TripRange::class)],
        ]);

        $range = TripRange::tryFrom($validated['range'] ?? '') ?? TripRange::All;

        $summary = $request->user()->ridesAsDriver()
            ->where('status', RideStatus::Completed)
            ->inRange($range)
            ->selectRaw('count(*) as trips, coalesce(sum(passengers_count), 0) as passengers')
            ->first();

        return response()->json([
            'range' => $range->value,
            'trips' => (int) $summary->trips,
            'passengers' => (int) $summary->passengers,
        ]);
    }
}
