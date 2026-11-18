<?php

use App\Models\DeviceToken;
use App\Models\DriverProfile;
use App\Models\EmergencyContact;
use App\Models\Ride;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

it('returns 401 without a token', function () {
    $this->deleteJson(route('api.v1.me.destroy'))->assertUnauthorized();
});

it('erases the person and what belongs to them', function () {
    $user = User::factory()->create(['phone' => '+639171234567']);
    EmergencyContact::factory()->for($user)->create();
    DeviceToken::factory()->for($user)->create();
    Ride::factory()->completed()->create(['passenger_id' => $user->id]);
    $token = $user->createToken('mobile')->plainTextToken;
    $other = User::factory()->create();
    EmergencyContact::factory()->for($other)->create();

    $this->withToken($token)->deleteJson(route('api.v1.me.destroy'))->assertNoContent();

    $this->assertModelMissing($user);
    $this->assertDatabaseCount('emergency_contacts', 1);
    $this->assertDatabaseCount('device_tokens', 0);
    $this->assertDatabaseCount('rides', 0);
    $this->assertDatabaseCount('personal_access_tokens', 0);
    $this->assertModelExists($other);
});

it('lets the same phone number sign up again as a new account', function () {
    $user = User::factory()->create(['phone' => '+639171234567']);

    $this->withToken($user->createToken('mobile')->plainTextToken)->deleteJson(route('api.v1.me.destroy'))->assertNoContent();

    expect(User::where('phone', '+639171234567')->exists())->toBeFalse();
    User::factory()->create(['phone' => '+639171234567']);
    expect(User::where('phone', '+639171234567')->count())->toBe(1);
});

it('erases the application of a driver with the photos, and keeps the trip for the passenger without a driver', function () {
    Storage::fake();

    $driver = User::factory()->driver()->create();
    $profile = DriverProfile::factory()->approved()->create(['user_id' => $driver->id]);
    Storage::put('driver-documents/'.$profile->id.'/photo.jpg', 'photo');
    $ride = Ride::factory()->completed($driver)->create();

    $this->withToken($driver->createToken('mobile')->plainTextToken)->deleteJson(route('api.v1.me.destroy'))->assertNoContent();

    $this->assertModelMissing($driver);
    $this->assertModelMissing($profile);
    Storage::assertMissing('driver-documents/'.$profile->id.'/photo.jpg');
    expect($ride->refresh()->driver_id)->toBeNull();
});

it('does not erase a person who is on a trip', function (string $state, string $role) {
    $person = User::factory()->create(['role' => $role]);
    $ride = Ride::factory()->{$state}($role === 'driver' ? $person : null)->create($role === 'passenger' ? ['passenger_id' => $person->id] : []);

    $this->withToken($person->createToken('mobile')->plainTextToken)
        ->deleteJson(route('api.v1.me.destroy'))
        ->assertConflict()
        ->assertJsonPath('message', 'Finish your trip first.');

    $this->assertModelExists($person);
    $this->assertModelExists($ride);
})->with([
    'passenger on the way' => ['inProgress', 'passenger'],
    'driver on the way' => ['inProgress', 'driver'],
    'driver coming' => ['accepted', 'driver'],
]);

it('does not erase an admin account', function () {
    $admin = User::factory()->admin()->create();

    $this->withToken($admin->createToken('mobile')->plainTextToken)->deleteJson(route('api.v1.me.destroy'))->assertForbidden();

    $this->assertModelExists($admin);
});
