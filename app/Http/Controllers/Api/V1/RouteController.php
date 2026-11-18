<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Routing\RoadRouteFinder;
use App\Support\Distance;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class RouteController extends Controller
{
    /**
     * The way by road between two points, for the map to draw: `points` are [latitude, longitude] pairs. When no
     * route can be found the answer is 503, and the app draws a straight line instead.
     */
    public function show(Request $request, RoadRouteFinder $finder): JsonResponse
    {
        $validated = $request->validate([
            'from_latitude' => ['required', 'numeric', 'between:-90,90'],
            'from_longitude' => ['required', 'numeric', 'between:-180,180'],
            'to_latitude' => ['required', 'numeric', 'between:-90,90'],
            'to_longitude' => ['required', 'numeric', 'between:-180,180'],
        ]);

        $from = [(float) $validated['from_latitude'], (float) $validated['from_longitude']];
        $to = [(float) $validated['to_latitude'], (float) $validated['to_longitude']];

        if (Distance::meters(...$from, ...$to) > config('routing.max_distance_meters')) {
            throw ValidationException::withMessages(['to_latitude' => 'The two points are too far apart.']);
        }

        $route = $finder->find(...$from, ...$to);

        if ($route === null) {
            return response()->json(['message' => 'No route could be found.'], 503);
        }

        return response()->json($route);
    }
}
