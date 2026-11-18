<?php

use App\Models\DeviceToken;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

describe('store', function () {
    it('remembers the phone of the person', function (string $role) {
        $user = User::factory()->create(['role' => $role]);

        Sanctum::actingAs($user);

        $this->postJson(route('api.v1.devices.store'), ['token' => 'ExponentPushToken[abc123]', 'platform' => 'android'])
            ->assertNoContent();

        expect($user->deviceTokens()->get(['token', 'platform'])->toArray())
            ->toBe([['token' => 'ExponentPushToken[abc123]', 'platform' => 'android']]);
    })->with(['passenger', 'driver']);

    it('accepts both spellings of an Expo token and no platform', function (string $token) {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $this->postJson(route('api.v1.devices.store'), ['token' => $token])->assertNoContent();

        expect($user->deviceTokens()->sole()->platform)->toBeNull();
    })->with(['ExponentPushToken[abc]', 'ExpoPushToken[abc]']);

    it('can be repeated every time the app opens', function () {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $this->postJson(route('api.v1.devices.store'), ['token' => 'ExponentPushToken[abc]', 'platform' => 'ios'])->assertNoContent();
        $this->postJson(route('api.v1.devices.store'), ['token' => 'ExponentPushToken[abc]', 'platform' => 'android'])->assertNoContent();

        $this->assertDatabaseCount('device_tokens', 1);
        expect($user->deviceTokens()->sole()->platform)->toBe('android');
    });

    it('moves a phone to the person who signed in on it now', function () {
        $before = User::factory()->create();
        DeviceToken::factory()->for($before)->create(['token' => 'ExponentPushToken[shared-phone]']);
        $now = User::factory()->driver()->create();

        Sanctum::actingAs($now);

        $this->postJson(route('api.v1.devices.store'), ['token' => 'ExponentPushToken[shared-phone]'])->assertNoContent();

        expect($before->deviceTokens()->count())->toBe(0);
        expect($now->deviceTokens()->count())->toBe(1);
    });

    it('lets a person use more than one phone', function () {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $this->postJson(route('api.v1.devices.store'), ['token' => 'ExponentPushToken[one]'])->assertNoContent();
        $this->postJson(route('api.v1.devices.store'), ['token' => 'ExponentPushToken[two]'])->assertNoContent();

        expect($user->deviceTokens()->count())->toBe(2);
    });

    it('returns 422 when the token is missing or not an Expo token', function (array $payload, string $error) {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson(route('api.v1.devices.store'), $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['token' => $error]);

        $this->assertDatabaseCount('device_tokens', 0);
    })->with([
        'missing' => [[], 'The token field is required.'],
        'plain text' => [['token' => 'hello'], 'This is not an Expo push token.'],
        'empty brackets' => [['token' => 'ExponentPushToken[]'], 'This is not an Expo push token.'],
    ]);

    it('returns 422 when the platform is not ios or android', function () {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson(route('api.v1.devices.store'), ['token' => 'ExponentPushToken[abc]', 'platform' => 'windows'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['platform']);
    });

    it('returns 401 without a token and 403 for an admin', function () {
        $this->postJson(route('api.v1.devices.store'), ['token' => 'ExponentPushToken[abc]'])->assertUnauthorized();

        Sanctum::actingAs(User::factory()->admin()->create());

        $this->postJson(route('api.v1.devices.store'), ['token' => 'ExponentPushToken[abc]'])->assertForbidden();
    });
});

describe('destroy', function () {
    it('forgets the phone of the person', function () {
        $user = User::factory()->create();
        DeviceToken::factory()->for($user)->create(['token' => 'ExponentPushToken[mine]']);
        DeviceToken::factory()->for($user)->create(['token' => 'ExponentPushToken[other-phone]']);

        Sanctum::actingAs($user);

        $this->deleteJson(route('api.v1.devices.destroy'), ['token' => 'ExponentPushToken[mine]'])->assertNoContent();

        expect($user->deviceTokens()->pluck('token')->all())->toBe(['ExponentPushToken[other-phone]']);
    });

    it('does not remove the phone of someone else', function () {
        $owner = User::factory()->create();
        DeviceToken::factory()->for($owner)->create(['token' => 'ExponentPushToken[theirs]']);

        Sanctum::actingAs(User::factory()->create());

        $this->deleteJson(route('api.v1.devices.destroy'), ['token' => 'ExponentPushToken[theirs]'])->assertNoContent();

        expect($owner->deviceTokens()->count())->toBe(1);
    });

    it('does not complain about a phone that is not saved', function () {
        Sanctum::actingAs(User::factory()->create());

        $this->deleteJson(route('api.v1.devices.destroy'), ['token' => 'ExponentPushToken[unknown]'])->assertNoContent();
    });

    it('returns 422 without a token and 401 without a login', function () {
        $this->deleteJson(route('api.v1.devices.destroy'), ['token' => 'ExponentPushToken[abc]'])->assertUnauthorized();

        Sanctum::actingAs(User::factory()->create());

        $this->deleteJson(route('api.v1.devices.destroy'))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['token']);
    });
});
