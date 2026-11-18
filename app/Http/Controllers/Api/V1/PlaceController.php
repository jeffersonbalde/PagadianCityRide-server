<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\PlaceResource;
use App\Models\Place;
use App\PlaceCategory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class PlaceController extends Controller
{
    private const PER_PAGE = 30;

    /**
     * List the places a passenger can choose as a destination, a page at a time. A
     * search matches the name or the area, a category narrows the list, and when the
     * person's spot is given the nearest places come first. Without it they are by name.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:50'],
            'category' => ['nullable', Rule::enum(PlaceCategory::class)],
            'latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_with:longitude'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:latitude'],
        ]);

        $search = $validated['search'] ?? null;

        $places = Place::query()
            ->where('is_active', true)
            ->when($search, fn ($query) => $query->where(
                fn ($query) => $query->where('name', 'like', '%'.$search.'%')->orWhere('area', 'like', '%'.$search.'%'),
            ))
            ->when($validated['category'] ?? null, fn ($query, string $category) => $query->where('category', $category))
            ->when(
                isset($validated['latitude'], $validated['longitude']),
                // The squares of the differences are enough to tell which place is nearer in one small city.
                fn ($query) => $query->orderByRaw(
                    '((latitude - ?) * (latitude - ?)) + ((longitude - ?) * (longitude - ?))',
                    [$validated['latitude'] ?? 0, $validated['latitude'] ?? 0, $validated['longitude'] ?? 0, $validated['longitude'] ?? 0],
                ),
            )
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(self::PER_PAGE);

        return PlaceResource::collection($places);
    }
}
