<?php

use App\AlertType;
use App\Models\User;
use App\Notifications\Alert;
use Laravel\Sanctum\Sanctum;

it('marks every alert of the person as read and nobody else\'s', function () {
    $passenger = User::factory()->create();
    $passenger->notify(new Alert(AlertType::RideExpired));
    $passenger->notify(new Alert(AlertType::RideAccepted));
    $other = User::factory()->create();
    $other->notify(new Alert(AlertType::RideExpired));

    Sanctum::actingAs($passenger);

    $this->postJson(route('api.v1.alerts.read-all.store'))->assertNoContent();

    expect($passenger->unreadNotifications()->count())->toBe(0);
    expect($other->unreadNotifications()->count())->toBe(1);

    $this->getJson(route('api.v1.alerts.index'))->assertJsonPath('unread_count', 0);
});

it('keeps the time of the alerts that were already read', function () {
    $this->freezeTime();
    $driver = User::factory()->driver()->create();
    $driver->notify(new Alert(AlertType::DriverApproved));
    $read = $driver->notifications()->sole();
    $read->markAsRead();

    $this->travel(1)->day();
    $driver->notify(new Alert(AlertType::RideCancelled));

    Sanctum::actingAs($driver);
    $this->postJson(route('api.v1.alerts.read-all.store'))->assertNoContent();

    expect($read->fresh()->read_at->toIso8601String())->toBe(now()->subDay()->toIso8601String());
});

it('works when there is nothing to read', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->postJson(route('api.v1.alerts.read-all.store'))->assertNoContent();
});

it('returns 401 without a token and 403 for an admin', function () {
    $this->postJson(route('api.v1.alerts.read-all.store'))->assertUnauthorized();

    Sanctum::actingAs(User::factory()->admin()->create());

    $this->postJson(route('api.v1.alerts.read-all.store'))->assertForbidden();
});
