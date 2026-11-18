<?php

use App\AlertType;
use App\Models\User;
use App\Notifications\Alert;
use Laravel\Sanctum\Sanctum;

it('marks one alert as read', function () {
    $this->freezeTime();
    $passenger = User::factory()->create();
    $passenger->notify(new Alert(AlertType::RideExpired));
    $passenger->notify(new Alert(AlertType::RideExpired));
    $alert = $passenger->notifications()->first();

    Sanctum::actingAs($passenger);

    $this->postJson(route('api.v1.alerts.read.store', $alert->id))
        ->assertOk()
        ->assertJsonPath('id', $alert->id)
        ->assertJsonPath('read_at', now()->toIso8601String());

    expect($passenger->unreadNotifications()->count())->toBe(1);
});

it('keeps the first time when the alert is read again', function () {
    $this->freezeTime();
    $passenger = User::factory()->create();
    $passenger->notify(new Alert(AlertType::RideExpired));
    $alert = $passenger->notifications()->sole();

    Sanctum::actingAs($passenger);
    $this->postJson(route('api.v1.alerts.read.store', $alert->id))->assertOk();

    $this->travel(1)->hour();
    $firstRead = $alert->fresh()->read_at->toIso8601String();

    $this->postJson(route('api.v1.alerts.read.store', $alert->id))
        ->assertOk()
        ->assertJsonPath('read_at', $firstRead);
});

it('returns 404 for the alert of someone else and leaves it unread', function () {
    $owner = User::factory()->create();
    $owner->notify(new Alert(AlertType::RideExpired));
    $alert = $owner->notifications()->sole();

    Sanctum::actingAs(User::factory()->create());

    $this->postJson(route('api.v1.alerts.read.store', $alert->id))->assertNotFound();

    expect($alert->fresh()->read_at)->toBeNull();
});

it('returns 404 for an alert that does not exist', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->postJson(route('api.v1.alerts.read.store', 'f47ac10b-58cc-4372-a567-0e02b2c3d479'))->assertNotFound();
});

it('returns 401 without a token', function () {
    $this->postJson(route('api.v1.alerts.read.store', 'f47ac10b-58cc-4372-a567-0e02b2c3d479'))->assertUnauthorized();
});
