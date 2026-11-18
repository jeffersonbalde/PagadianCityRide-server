<?php

use App\Models\Place;
use App\PlaceCategory;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

/**
 * @param  array<string, string>  $tags
 * @return array<string, mixed>
 */
function osmNode(int $id, array $tags, float $latitude = 7.8231, float $longitude = 123.4402): array
{
    return ['type' => 'node', 'id' => $id, 'lat' => $latitude, 'lon' => $longitude, 'tags' => $tags];
}

/**
 * @param  list<array<string, mixed>>  $elements
 */
function fakeOverpass(array $elements): void
{
    Http::fake(['*' => Http::response(['elements' => $elements])]);
}

it('adds the places of OpenStreetMap with their category', function () {
    fakeOverpass([
        osmNode(1, ['name' => 'Gaisano Grand Mall', 'shop' => 'mall']),
        osmNode(2, ['name' => 'Pagadian Medical Center', 'amenity' => 'hospital']),
        osmNode(3, ['name' => 'Zamboanga del Sur National High School', 'amenity' => 'school']),
        osmNode(4, ['name' => 'Immaculate Conception Cathedral', 'amenity' => 'place_of_worship']),
        osmNode(5, ['name' => 'Gatas', 'place' => 'suburb']),
    ]);

    $this->artisan('places:import')
        ->expectsOutputToContain('5 place(s) added')
        ->assertSuccessful();

    expect(Place::count())->toBe(5);
    expect(Place::where('name', 'Gaisano Grand Mall')->sole())
        ->category->toBe(PlaceCategory::Mall)
        ->source->toBe('osm')
        ->osm_id->toBe('node/1');
    expect(Place::where('name', 'Gatas')->sole()->category)->toBe(PlaceCategory::Area);
});

it('asks Overpass with a name for the app', function () {
    fakeOverpass([]);

    $this->artisan('places:import')->assertSuccessful();

    Http::assertSent(fn ($request) => $request->hasHeader('User-Agent', config('places.user_agent'))
        && str_contains($request['data'], 'out center tags'));
});

it('uses the middle of a building or an area', function () {
    fakeOverpass([
        ['type' => 'way', 'id' => 9, 'center' => ['lat' => 7.8301, 'lon' => 123.4511], 'tags' => ['name' => 'Gaisano Grand Mall', 'shop' => 'mall']],
    ]);

    $this->artisan('places:import')->assertSuccessful();

    expect(Place::sole())
        ->osm_id->toBe('way/9')
        ->latitude->toBe(7.8301)
        ->longitude->toBe(123.4511);
});

it('leaves out what has no name, what is not a place to go to and what has no spot', function () {
    fakeOverpass([
        osmNode(1, ['shop' => 'mall']),
        osmNode(2, ['name' => 'A bench', 'amenity' => 'bench']),
        ['type' => 'relation', 'id' => 3, 'tags' => ['name' => 'Somewhere', 'amenity' => 'hospital']],
        osmNode(4, ['name' => 'Real Hospital', 'amenity' => 'hospital']),
    ]);

    $this->artisan('places:import')->assertSuccessful();

    expect(Place::pluck('name')->all())->toBe(['Real Hospital']);
});

it('puts a place under the nearest barangay', function () {
    fakeOverpass([
        osmNode(1, ['name' => 'Gatas', 'place' => 'suburb'], 7.8326, 123.4311),
        osmNode(2, ['name' => 'Balangasan', 'place' => 'suburb'], 7.8231, 123.4402),
        osmNode(3, ['name' => 'Port Market', 'amenity' => 'marketplace'], 7.8320, 123.4315),
        osmNode(4, ['name' => 'Rural Clinic', 'amenity' => 'clinic', 'addr:suburb' => 'Tiguma'], 7.8320, 123.4315),
    ]);

    $this->artisan('places:import')->assertSuccessful();

    expect(Place::where('name', 'Port Market')->sole()->area)->toBe('Gatas');
    expect(Place::where('name', 'Rural Clinic')->sole()->area)->toBe('Tiguma');
    expect(Place::where('name', 'Gatas')->sole()->area)->toBe('Pagadian City');
});

it('updates a place that is already there and keeps it hidden when an admin hid it', function () {
    $place = Place::factory()->create(['name' => 'Old name', 'osm_id' => 'node/1', 'source' => 'osm', 'is_active' => false]);

    fakeOverpass([osmNode(1, ['name' => 'New name', 'amenity' => 'hospital'])]);

    $this->artisan('places:import')
        ->expectsOutputToContain('0 place(s) added, 1 updated')
        ->assertSuccessful();

    expect($place->refresh())
        ->name->toBe('New name')
        ->category->toBe(PlaceCategory::Hospital)
        ->is_active->toBeFalse();
    expect(Place::count())->toBe(1);
});

it('does not add a place that was added by hand at the same spot', function () {
    Place::factory()->create(['name' => 'City Hall', 'source' => 'seed', 'latitude' => 7.8249, 'longitude' => 123.4389]);

    fakeOverpass([
        osmNode(1, ['name' => 'city hall', 'amenity' => 'townhall'], 7.8250, 123.4390),
        osmNode(2, ['name' => 'City Hall', 'amenity' => 'townhall'], 7.9000, 123.5000),
    ]);

    $this->artisan('places:import')
        ->expectsOutputToContain('1 place(s) added, 0 updated, 1 skipped')
        ->assertSuccessful();

    expect(Place::count())->toBe(2);
});

it('searches inside the relation of the city when its number is set', function () {
    config(['places.relation_id' => 12345]);
    fakeOverpass([]);

    $this->artisan('places:import')->assertSuccessful();

    Http::assertSent(fn ($request) => str_contains($request['data'], '(area:3600012345)'));
});

it('fails with a message when OpenStreetMap does not answer', function () {
    Sleep::fake();
    Http::fake(['*' => Http::response('busy', 504)]);

    $this->artisan('places:import')
        ->expectsOutputToContain('OpenStreetMap did not answer')
        ->assertFailed();

    expect(Place::count())->toBe(0);
});

it('knows the category of a place in OpenStreetMap', function (array $tags, ?PlaceCategory $category) {
    expect(PlaceCategory::fromOpenStreetMapTags($tags))->toBe($category);
})->with([
    'mall' => [['shop' => 'mall'], PlaceCategory::Mall],
    'clinic' => [['amenity' => 'clinic'], PlaceCategory::Hospital],
    'college' => [['amenity' => 'college'], PlaceCategory::School],
    'church' => [['amenity' => 'place_of_worship'], PlaceCategory::Church],
    'supermarket' => [['shop' => 'supermarket'], PlaceCategory::Market],
    'bus station' => [['amenity' => 'bus_station'], PlaceCategory::Terminal],
    'airport' => [['aeroway' => 'aerodrome'], PlaceCategory::Terminal],
    'police' => [['amenity' => 'police'], PlaceCategory::Government],
    'purok' => [['place' => 'neighbourhood'], PlaceCategory::Area],
    'attraction' => [['tourism' => 'attraction'], PlaceCategory::Other],
    'a bench' => [['amenity' => 'bench'], null],
]);
