<?php

use App\Models\Place;
use App\PlaceCategory;
use Database\Seeders\PlaceSeeder;

it('adds the places of the destination list', function () {
    $this->seed(PlaceSeeder::class);

    expect(Place::count())->toBe(13);
    expect(Place::where('name', 'City Hall')->sole()->area)->toBe('Balangasan');
});

it('gives every place a category', function () {
    $this->seed(PlaceSeeder::class);

    expect(Place::where('name', 'Cathedral')->sole()->category)->toBe(PlaceCategory::Church);
    expect(Place::where('name', 'Medical Center')->sole()->category)->toBe(PlaceCategory::Hospital);
    expect(Place::where('source', '!=', 'seed')->count())->toBe(0);
});

it('does not add a place twice when it runs again', function () {
    $this->seed(PlaceSeeder::class);
    $this->seed(PlaceSeeder::class);

    expect(Place::count())->toBe(13);
});

it('puts every place in Pagadian City', function () {
    $this->seed(PlaceSeeder::class);

    Place::all()->each(function (Place $place) {
        expect($place->latitude)->toBeBetween(7.7, 7.9);
        expect($place->longitude)->toBeBetween(123.3, 123.5);
    });
});
