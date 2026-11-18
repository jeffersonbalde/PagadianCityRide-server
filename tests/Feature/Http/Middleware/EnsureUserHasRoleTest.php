<?php

use App\Models\DriverProfile;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

dataset('driver routes', [
    'profile' => fn () => ['get', route('api.v1.driver.profile.show')],
    'document' => fn () => ['get', route('api.v1.driver.documents.show', 'photo')],
    'submission' => fn () => ['post', route('api.v1.driver.submission.store')],
]);

dataset('admin routes', [
    'list' => fn () => ['get', route('api.v1.admin.driver-profiles.index')],
    'show' => fn () => ['get', route('api.v1.admin.driver-profiles.show', DriverProfile::factory()->create())],
    'approval' => fn () => ['post', route('api.v1.admin.driver-profiles.approval.store', DriverProfile::factory()->submitted()->create())],
]);

it('returns 401 on a driver route without a token', function (array $route) {
    [$method, $url] = $route;

    $this->json($method, $url)->assertUnauthorized();
})->with('driver routes');

it('returns 403 on a driver route for a passenger or an admin', function (array $route, string $state) {
    [$method, $url] = $route;

    Sanctum::actingAs(User::factory()->{$state}()->create());

    $this->json($method, $url)
        ->assertForbidden()
        ->assertJsonPath('message', 'This action is not available for your account.');
})->with('driver routes')->with(['passenger' => 'unverified', 'admin' => 'admin']);

it('returns 401 on an admin route without a token', function (array $route) {
    [$method, $url] = $route;

    $this->json($method, $url)->assertUnauthorized();
})->with('admin routes');

it('returns 403 on an admin route for a passenger or a driver', function (array $route, string $state) {
    [$method, $url] = $route;

    Sanctum::actingAs(User::factory()->{$state}()->create());

    $this->json($method, $url)
        ->assertForbidden()
        ->assertJsonPath('message', 'This action is not available for your account.');
})->with('admin routes')->with(['passenger' => 'unverified', 'driver' => 'driver']);
