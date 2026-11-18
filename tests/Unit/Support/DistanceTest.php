<?php

use App\Support\Distance;

it('measures a degree of latitude as about 111 km', function () {
    expect(Distance::meters(0, 0, 1, 0))->toBe(111195);
});

it('measures the distance between two points in Pagadian City', function () {
    expect(Distance::meters(7.8231, 123.4402, 7.8249, 123.4389))->toBeBetween(240, 260);
});

it('measures zero between a point and itself', function () {
    expect(Distance::meters(7.8231, 123.4402, 7.8231, 123.4402))->toBe(0);
});

it('measures the same distance in both directions', function () {
    expect(Distance::meters(7.8231, 123.4402, 7.8326, 123.4311))
        ->toBe(Distance::meters(7.8326, 123.4311, 7.8231, 123.4402));
});

it('draws a bounding box that holds every point inside the radius', function () {
    $box = Distance::boundingBox(7.8231, 123.4402, 3000);

    foreach ([[0, 3000], [0, -3000], [3000, 0], [-3000, 0]] as [$north, $east]) {
        $latitude = 7.8231 + rad2deg($north / 6371000);
        $longitude = 123.4402 + rad2deg($east / (6371000 * cos(deg2rad(7.8231))));

        expect($latitude)->toBeBetween($box['minLatitude'], $box['maxLatitude']);
        expect($longitude)->toBeBetween($box['minLongitude'], $box['maxLongitude']);
    }
});

it('draws a bounding box that does not hold a point far outside the radius', function () {
    $box = Distance::boundingBox(7.8231, 123.4402, 3000);

    expect(7.8231 + 0.1)->toBeGreaterThan($box['maxLatitude']);
    expect(123.4402 + 0.1)->toBeGreaterThan($box['maxLongitude']);
});
