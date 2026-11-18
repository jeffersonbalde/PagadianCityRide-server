<?php

namespace App\Actions\Place;

use App\Models\Place;
use App\PlaceCategory;
use App\Services\Places\OverpassClient;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Brings the places of Pagadian City from OpenStreetMap into the places table. A place
 * that is already there is updated, a place an admin hid stays hidden, and a place that
 * was added by hand with the same name and spot is not added a second time.
 */
class ImportOpenStreetMapPlaces
{
    /** About 300 meters, in degrees. */
    private const SAME_SPOT_DEGREES = 0.003;

    public function __construct(private OverpassClient $client) {}

    /**
     * @return array{created: int, updated: int, skipped: int}
     */
    public function handle(): array
    {
        $candidates = collect($this->client->places())
            ->map(fn (array $element): ?array => $this->candidate($element))
            ->filter()
            ->unique('osm_id')
            ->values();

        $areas = $candidates->where('category', PlaceCategory::Area)->values();

        $result = ['created' => 0, 'updated' => 0, 'skipped' => 0];

        foreach ($candidates as $candidate) {
            $existing = Place::query()->where('osm_id', $candidate['osm_id'])->first();

            if ($existing === null && $this->alreadyAddedByHand($candidate)) {
                $result['skipped']++;

                continue;
            }

            $attributes = [
                'name' => $candidate['name'],
                'area' => $candidate['area'] ?? $this->nearestArea($candidate, $areas),
                'category' => $candidate['category'],
                'latitude' => $candidate['latitude'],
                'longitude' => $candidate['longitude'],
                'source' => 'osm',
            ];

            if ($existing === null) {
                Place::create($attributes + ['osm_id' => $candidate['osm_id']]);
                $result['created']++;
            } else {
                $existing->update($attributes);
                $result['updated']++;
            }
        }

        return $result;
    }

    /**
     * @param  array{type: string, id: int, lat?: float, lon?: float, center?: array{lat: float, lon: float}, tags?: array<string, string>}  $element
     * @return array{osm_id: string, name: string, category: PlaceCategory, latitude: float, longitude: float, area: ?string}|null
     */
    private function candidate(array $element): ?array
    {
        $tags = $element['tags'] ?? [];
        $name = trim($tags['name'] ?? $tags['name:en'] ?? '');
        $category = PlaceCategory::fromOpenStreetMapTags($tags);
        $latitude = $element['lat'] ?? $element['center']['lat'] ?? null;
        $longitude = $element['lon'] ?? $element['center']['lon'] ?? null;

        if ($name === '' || $category === null || $latitude === null || $longitude === null) {
            return null;
        }

        $area = $tags['addr:suburb'] ?? $tags['addr:barangay'] ?? null;

        return [
            'osm_id' => $element['type'].'/'.$element['id'],
            'name' => Str::limit($name, 100, ''),
            'category' => $category,
            'latitude' => (float) $latitude,
            'longitude' => (float) $longitude,
            'area' => $category === PlaceCategory::Area ? 'Pagadian City' : ($area ? Str::limit($area, 100, '') : null),
        ];
    }

    /**
     * @param  array{name: string, latitude: float, longitude: float}  $candidate
     */
    private function alreadyAddedByHand(array $candidate): bool
    {
        return Place::query()
            ->where('source', '!=', 'osm')
            ->whereRaw('lower(name) = ?', [Str::lower($candidate['name'])])
            ->whereBetween('latitude', [$candidate['latitude'] - self::SAME_SPOT_DEGREES, $candidate['latitude'] + self::SAME_SPOT_DEGREES])
            ->whereBetween('longitude', [$candidate['longitude'] - self::SAME_SPOT_DEGREES, $candidate['longitude'] + self::SAME_SPOT_DEGREES])
            ->exists();
    }

    /**
     * The barangay or purok that is nearest to a place, for the line under its name.
     *
     * @param  array{latitude: float, longitude: float}  $candidate
     * @param  Collection<int, array{name: string, latitude: float, longitude: float}>  $areas
     */
    private function nearestArea(array $candidate, Collection $areas): string
    {
        $nearest = $areas->sortBy(
            fn (array $area): float => ($area['latitude'] - $candidate['latitude']) ** 2 + ($area['longitude'] - $candidate['longitude']) ** 2,
        )->first();

        return $nearest['name'] ?? 'Pagadian City';
    }
}
