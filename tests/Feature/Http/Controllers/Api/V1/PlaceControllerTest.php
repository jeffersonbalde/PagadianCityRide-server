<?php

use App\Models\Place;
use App\Models\User;
use App\PlaceCategory;
use Laravel\Sanctum\Sanctum;

it('returns 401 without a token', function () {
    $this->getJson(route('api.v1.places.index'))->assertUnauthorized();
});

it('lists the places by name', function () {
    Place::factory()->create(['name' => 'Port', 'area' => 'Gatas', 'category' => PlaceCategory::Terminal, 'latitude' => 7.8326, 'longitude' => 123.4311]);
    Place::factory()->create(['name' => 'Airport', 'area' => 'Tiguma']);
    Place::factory()->create(['name' => 'City Hall', 'area' => 'Balangasan']);

    Sanctum::actingAs(User::factory()->create());

    $this->getJson(route('api.v1.places.index'))
        ->assertOk()
        ->assertJsonCount(3, 'data')
        ->assertJsonPath('data.0.name', 'Airport')
        ->assertJsonPath('data.1.name', 'City Hall')
        ->assertJsonPath('data.2', [
            'id' => Place::where('name', 'Port')->value('id'),
            'name' => 'Port',
            'area' => 'Gatas',
            'category' => 'terminal',
            'latitude' => 7.8326,
            'longitude' => 123.4311,
        ]);
});

it('lets a driver list the places too', function () {
    Place::factory()->create();

    Sanctum::actingAs(User::factory()->driver()->create());

    $this->getJson(route('api.v1.places.index'))->assertOk()->assertJsonCount(1, 'data');
});

it('finds a place by name or by area', function (string $search, array $names) {
    Place::factory()->create(['name' => 'Public market', 'area' => 'Rizal Ave.']);
    Place::factory()->create(['name' => 'Cathedral', 'area' => 'Rizal Ave.']);
    Place::factory()->create(['name' => 'Port', 'area' => 'Gatas']);

    Sanctum::actingAs(User::factory()->create());

    $this->getJson(route('api.v1.places.index', ['search' => $search]))
        ->assertOk()
        ->assertJsonPath('data.*.name', $names);
})->with([
    'by name' => ['market', ['Public market']],
    'by area' => ['rizal', ['Cathedral', 'Public market']],
    'nothing matches' => ['airport', []],
]);

it('lists only the places of one category', function () {
    Place::factory()->create(['name' => 'Gaisano Mall', 'category' => PlaceCategory::Mall]);
    Place::factory()->create(['name' => 'Balangasan', 'category' => PlaceCategory::Area]);
    Place::factory()->create(['name' => 'Cathedral', 'category' => PlaceCategory::Church]);

    Sanctum::actingAs(User::factory()->create());

    $this->getJson(route('api.v1.places.index', ['category' => 'mall']))
        ->assertOk()
        ->assertJsonPath('data.*.name', ['Gaisano Mall']);
});

it('puts the nearest places first when the spot of the person is given', function () {
    Place::factory()->create(['name' => 'Far', 'latitude' => 7.9000000, 'longitude' => 123.5000000]);
    Place::factory()->create(['name' => 'Near', 'latitude' => 7.8231000, 'longitude' => 123.4402000]);
    Place::factory()->create(['name' => 'Middle', 'latitude' => 7.8500000, 'longitude' => 123.4600000]);

    Sanctum::actingAs(User::factory()->create());

    $this->getJson(route('api.v1.places.index', ['latitude' => 7.8231, 'longitude' => 123.4402]))
        ->assertOk()
        ->assertJsonPath('data.*.name', ['Near', 'Middle', 'Far']);
});

it('does not list a place an admin hid', function () {
    Place::factory()->create(['name' => 'Shown']);
    Place::factory()->create(['name' => 'Hidden', 'is_active' => false]);

    Sanctum::actingAs(User::factory()->create());

    $this->getJson(route('api.v1.places.index'))
        ->assertOk()
        ->assertJsonPath('data.*.name', ['Shown']);
});

it('gives the places a page at a time', function () {
    Place::factory()->count(35)->create();

    Sanctum::actingAs(User::factory()->create());

    $this->getJson(route('api.v1.places.index'))
        ->assertOk()
        ->assertJsonCount(30, 'data')
        ->assertJsonPath('meta.last_page', 2);

    $this->getJson(route('api.v1.places.index', ['page' => 2]))
        ->assertOk()
        ->assertJsonCount(5, 'data');
});

it('returns 422 for a search that is too long, an unknown category or half a spot', function (array $query, string $field) {
    Sanctum::actingAs(User::factory()->create());

    $this->getJson(route('api.v1.places.index', $query))
        ->assertUnprocessable()
        ->assertJsonValidationErrors([$field]);
})->with([
    'long search' => [['search' => str_repeat('a', 51)], 'search'],
    'unknown category' => [['category' => 'castle'], 'category'],
    'latitude alone' => [['latitude' => 7.82], 'longitude'],
]);
