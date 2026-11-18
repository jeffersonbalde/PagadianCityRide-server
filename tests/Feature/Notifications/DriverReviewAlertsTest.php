<?php

use App\AlertType;
use App\Models\DeviceToken;
use App\Models\DriverProfile;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('tells the driver in the app and by push when the application is approved', function () {
    $driver = User::factory()->driver()->create();
    DeviceToken::factory()->for($driver)->create(['token' => 'ExponentPushToken[driver]']);
    $profile = DriverProfile::factory()->for($driver)->submitted()->create();

    Sanctum::actingAs(User::factory()->admin()->create());
    $this->postJson(route('api.v1.admin.driver-profiles.approval.store', $profile))->assertOk();

    expect($driver->notifications()->sole()->data)->toMatchArray([
        'type' => 'driver.approved',
        'title' => 'You are approved',
        'ride_id' => null,
    ]);
    expect(push()->sent())->toHaveCount(1);
    expect(push()->sent()[0])->toMatchArray(['to' => 'ExponentPushToken[driver]', 'title' => 'You are approved']);
});

it('tells the driver when the application needs changes', function () {
    $driver = User::factory()->driver()->create();
    $profile = DriverProfile::factory()->for($driver)->submitted()->create();

    Sanctum::actingAs(User::factory()->admin()->create());
    $this->postJson(route('api.v1.admin.driver-profiles.rejection.store', $profile), ['reason' => 'Not readable.'])->assertOk();

    expect($driver->notifications()->sole()->data['type'])->toBe(AlertType::DriverRejected->value);
});

it('does not tell the driver about an application that was not reviewed', function () {
    $driver = User::factory()->driver()->create();
    $profile = DriverProfile::factory()->for($driver)->approved()->create();

    Sanctum::actingAs(User::factory()->admin()->create());
    $this->postJson(route('api.v1.admin.driver-profiles.approval.store', $profile))->assertForbidden();

    expect($driver->notifications()->count())->toBe(0);
});

it('lets the driver read the alert in the alert list', function () {
    $driver = User::factory()->driver()->create();
    $profile = DriverProfile::factory()->for($driver)->submitted()->create();

    Sanctum::actingAs(User::factory()->admin()->create());
    $this->postJson(route('api.v1.admin.driver-profiles.approval.store', $profile))->assertOk();

    Sanctum::actingAs($driver);
    $this->getJson(route('api.v1.alerts.index'))
        ->assertOk()
        ->assertJsonPath('data.0.type', 'driver.approved')
        ->assertJsonPath('unread_count', 1);
});
