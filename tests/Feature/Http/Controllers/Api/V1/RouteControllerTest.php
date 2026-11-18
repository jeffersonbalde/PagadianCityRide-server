<?php

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;

function routeQuery(array $overrides = []): array
{
    return array_merge([
        'from_latitude' => 7.8231,
        'from_longitude' => 123.4402,
        'to_latitude' => 7.8249,
        'to_longitude' => 123.4389,
    ], $overrides);
}

function osrmResponse(): array
{
    return [
        'code' => 'Ok',
        'routes' => [[
            'distance' => 2310.4,
            'duration' => 301.6,
            'geometry' => ['type' => 'LineString', 'coordinates' => [
                [123.4402, 7.8231],
                [123.44021, 7.82311],
                [123.44021, 7.82311],
                [123.4395, 7.8240],
                [123.4389, 7.8249],
            ]],
        ]],
    ];
}

beforeEach(function () {
    Cache::flush();
    Http::preventStrayRequests();
});

it('gives the way by road as latitude and longitude pairs', function () {
    Http::fake(['router.project-osrm.org/*' => Http::response(osrmResponse())]);
    Sanctum::actingAs(User::factory()->create());

    $this->getJson(route('api.v1.route.show', routeQuery()))
        ->assertOk()
        ->assertExactJson([
            'distance_meters' => 2310,
            'duration_seconds' => 302,
            'points' => [[7.8231, 123.4402], [7.82311, 123.44021], [7.824, 123.4395], [7.8249, 123.4389]],
        ]);

    Http::assertSent(fn ($request) => str_contains($request->url(), '/route/v1/driving/123.4402,7.8231;123.4389,7.8249')
        && $request['geometries'] === 'geojson');
});

it('asks the routing server once for the same trip', function () {
    Http::fake(['router.project-osrm.org/*' => Http::response(osrmResponse())]);
    Sanctum::actingAs(User::factory()->create());

    $this->getJson(route('api.v1.route.show', routeQuery()))->assertOk();
    $this->getJson(route('api.v1.route.show', routeQuery()))->assertOk();

    Http::assertSentCount(1);
});

it('uses the routing server that is set in the settings', function () {
    config(['routing.osrm_url' => 'https://osrm.example.test/']);
    Http::fake(['osrm.example.test/*' => Http::response(osrmResponse())]);
    Sanctum::actingAs(User::factory()->create());

    $this->getJson(route('api.v1.route.show', routeQuery()))->assertOk();

    Http::assertSent(fn ($request) => str_starts_with($request->url(), 'https://osrm.example.test/route/v1/driving/'));
});

it('is a 503 when the routing server has no route, and it does not keep that answer', function () {
    Http::fake(['router.project-osrm.org/*' => Http::sequence()
        ->push(['code' => 'NoRoute', 'routes' => []], 200)
        ->push(osrmResponse())]);
    Sanctum::actingAs(User::factory()->create());

    $this->getJson(route('api.v1.route.show', routeQuery()))->assertStatus(503);
    $this->getJson(route('api.v1.route.show', routeQuery()))->assertOk();
});

it('is a 503 when the routing server is down', function () {
    Http::fake(['router.project-osrm.org/*' => Http::response('busy', 504)]);
    Sanctum::actingAs(User::factory()->create());

    $this->getJson(route('api.v1.route.show', routeQuery()))->assertStatus(503);
});

it('is a 503 when the routing server cannot be reached', function () {
    Http::fake(['router.project-osrm.org/*' => Http::failedConnection()]);
    Sanctum::actingAs(User::factory()->create());

    $this->getJson(route('api.v1.route.show', routeQuery()))->assertStatus(503);
});

it('refuses two points that are far apart, without asking the routing server', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->getJson(route('api.v1.route.show', routeQuery(['to_latitude' => 8.5, 'to_longitude' => 124.6])))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('to_latitude');

    Http::assertNothingSent();
});

it('needs all four numbers, in range', function (array $override) {
    Sanctum::actingAs(User::factory()->create());

    $this->getJson(route('api.v1.route.show', routeQuery($override)))->assertUnprocessable();
})->with([
    'no start' => [['from_latitude' => null]],
    'latitude out of range' => [['to_latitude' => 91]],
    'longitude is not a number' => [['to_longitude' => 'abc']],
]);

it('returns 401 without a token and 403 for an admin', function () {
    $this->getJson(route('api.v1.route.show', routeQuery()))->assertUnauthorized();

    Sanctum::actingAs(User::factory()->admin()->create());

    $this->getJson(route('api.v1.route.show', routeQuery()))->assertForbidden();
});
