<?php

use App\Models\PhoneVerificationCode;
use App\Models\User;
use App\UserRole;

const TEST_PHONE = '+639171234567';
const TEST_CODE = '482915';

describe('store', function () {
    it('creates a passenger and returns a token on the first sign in', function () {
        $code = PhoneVerificationCode::factory()->create(['phone' => TEST_PHONE, 'code' => TEST_CODE]);

        $response = $this->postJson(route('api.v1.auth.token.store'), [
            'phone' => '0917 123 4567',
            'code' => TEST_CODE,
            'device_name' => 'Maria phone',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['token', 'user' => ['id', 'name', 'phone', 'role']])
            ->assertJsonPath('user.phone', TEST_PHONE)
            ->assertJsonPath('user.role', 'passenger');

        $user = User::where('phone', TEST_PHONE)->sole();

        expect($user->role)->toBe(UserRole::Passenger);
        expect($user->phone_verified_at)->not->toBeNull();
        expect($user->tokens()->sole()->name)->toBe('Maria phone');
        expect($code->fresh()->consumed_at)->not->toBeNull();
    });

    it('gives a blocked person no token, even with the right code', function () {
        $user = User::factory()->blocked()->create(['phone' => TEST_PHONE]);
        PhoneVerificationCode::factory()->create(['phone' => TEST_PHONE, 'code' => TEST_CODE]);

        $this->postJson(route('api.v1.auth.token.store'), ['phone' => TEST_PHONE, 'code' => TEST_CODE])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['code' => 'blocked']);

        expect($user->tokens()->count())->toBe(0);
    });

    it('creates a driver when the person signs up as a driver', function () {
        PhoneVerificationCode::factory()->create(['phone' => TEST_PHONE, 'code' => TEST_CODE]);

        $this->postJson(route('api.v1.auth.token.store'), [
            'phone' => TEST_PHONE,
            'code' => TEST_CODE,
            'role' => 'driver',
        ])->assertOk()->assertJsonPath('user.role', 'driver');

        expect(User::where('phone', TEST_PHONE)->sole()->role)->toBe(UserRole::Driver);
    });

    it('keeps the role of a person who already has an account', function () {
        $driver = User::factory()->driver()->create(['phone' => TEST_PHONE]);
        PhoneVerificationCode::factory()->create(['phone' => TEST_PHONE, 'code' => TEST_CODE]);

        $this->postJson(route('api.v1.auth.token.store'), [
            'phone' => TEST_PHONE,
            'code' => TEST_CODE,
            'role' => 'passenger',
        ])->assertOk()->assertJsonPath('user.id', $driver->id)->assertJsonPath('user.role', 'driver');

        $this->assertDatabaseCount('users', 1);
    });

    it('returns 422 when the role is not one a person can pick', function (string $role) {
        PhoneVerificationCode::factory()->create(['phone' => TEST_PHONE, 'code' => TEST_CODE]);

        $this->postJson(route('api.v1.auth.token.store'), [
            'phone' => TEST_PHONE,
            'code' => TEST_CODE,
            'role' => $role,
        ])->assertUnprocessable()->assertJsonValidationErrors(['role']);

        $this->assertDatabaseCount('users', 0);
    })->with([
        'admin' => 'admin',
        'unknown' => 'owner',
    ]);

    it('returns 422 and counts the attempt when the code is wrong', function () {
        $code = PhoneVerificationCode::factory()->create(['phone' => TEST_PHONE, 'code' => TEST_CODE]);

        $this->postJson(route('api.v1.auth.token.store'), ['phone' => TEST_PHONE, 'code' => '000000'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['code' => 'The code is wrong or has expired.']);

        expect($code->fresh()->attempts)->toBe(1);
        expect($code->fresh()->consumed_at)->toBeNull();
        $this->assertDatabaseCount('users', 0);
    });

    it('returns 422 when the code expired', function () {
        PhoneVerificationCode::factory()->expired()->create(['phone' => TEST_PHONE, 'code' => TEST_CODE]);

        $this->postJson(route('api.v1.auth.token.store'), ['phone' => TEST_PHONE, 'code' => TEST_CODE])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['code' => 'The code is wrong or has expired.']);

        $this->assertDatabaseCount('users', 0);
    });

    it('returns 422 when the code was already used', function () {
        PhoneVerificationCode::factory()->consumed()->create(['phone' => TEST_PHONE, 'code' => TEST_CODE]);

        $this->postJson(route('api.v1.auth.token.store'), ['phone' => TEST_PHONE, 'code' => TEST_CODE])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['code']);
    });

    it('returns 422 when the phone number has no code', function () {
        $this->postJson(route('api.v1.auth.token.store'), ['phone' => TEST_PHONE, 'code' => TEST_CODE])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['code' => 'The code is wrong or has expired.']);
    });

    it('stops accepting a code after five wrong attempts, even the right one', function () {
        PhoneVerificationCode::factory()->create(['phone' => TEST_PHONE, 'code' => TEST_CODE]);

        foreach (range(1, PhoneVerificationCode::MAX_ATTEMPTS) as $attempt) {
            $this->postJson(route('api.v1.auth.token.store'), ['phone' => TEST_PHONE, 'code' => '000000'])
                ->assertUnprocessable();
        }

        $this->postJson(route('api.v1.auth.token.store'), ['phone' => TEST_PHONE, 'code' => TEST_CODE])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['code' => 'The code is wrong or has expired.']);

        $this->assertDatabaseCount('users', 0);
    });

    it('does not accept the code of another phone number', function () {
        PhoneVerificationCode::factory()->create(['phone' => '+639181234567', 'code' => TEST_CODE]);

        $this->postJson(route('api.v1.auth.token.store'), ['phone' => TEST_PHONE, 'code' => TEST_CODE])
            ->assertUnprocessable();

        $this->assertDatabaseCount('users', 0);
    });

    it('returns 422 when the code does not have 6 digits', function (string $code) {
        $this->postJson(route('api.v1.auth.token.store'), ['phone' => TEST_PHONE, 'code' => $code])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['code' => 'The code has 6 digits.']);
    })->with([
        'too short' => '12345',
        'too long' => '1234567',
        'letters' => 'abcdef',
    ]);

    it('returns 429 after ten tries from one device in a minute', function () {
        foreach (range(1, 10) as $attempt) {
            $this->postJson(route('api.v1.auth.token.store'), ['phone' => TEST_PHONE, 'code' => '000000'])
                ->assertUnprocessable();
        }

        $this->postJson(route('api.v1.auth.token.store'), ['phone' => TEST_PHONE, 'code' => '000000'])
            ->assertTooManyRequests();
    });
});

describe('destroy', function () {
    it('revokes the token that made the request', function () {
        $user = User::factory()->create();
        $token = $user->createToken('mobile')->plainTextToken;
        $user->createToken('other phone');

        $this->withToken($token)
            ->deleteJson(route('api.v1.auth.token.destroy'))
            ->assertNoContent();

        expect($user->tokens()->pluck('name')->all())->toBe(['other phone']);
    });

    it('returns 401 without a token', function () {
        $this->deleteJson(route('api.v1.auth.token.destroy'))->assertUnauthorized();
    });
});

describe('testing shortcut', function () {
    it('lets any code sign in when it is on and the server is local', function () {
        config(['services.sms.accept_any_code' => true]);
        $this->app['env'] = 'local';

        $this->postJson(route('api.v1.auth.token.store'), ['phone' => TEST_PHONE, 'code' => '000000', 'role' => 'driver'])
            ->assertOk()
            ->assertJsonPath('user.phone', TEST_PHONE)
            ->assertJsonPath('user.role', 'driver');

        expect(User::where('phone', TEST_PHONE)->sole()->phone_verified_at)->not->toBeNull();
    });

    it('does nothing outside a local server, even when the setting is on', function (string $environment) {
        config(['services.sms.accept_any_code' => true]);
        $this->app['env'] = $environment;

        $this->postJson(route('api.v1.auth.token.store'), ['phone' => TEST_PHONE, 'code' => '000000'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['code']);

        $this->assertDatabaseCount('users', 0);
    })->with(['production', 'staging', 'testing']);

    it('is off by default, so a wrong code is still refused', function () {
        $this->app['env'] = 'local';
        PhoneVerificationCode::factory()->create(['phone' => TEST_PHONE, 'code' => TEST_CODE]);

        $this->postJson(route('api.v1.auth.token.store'), ['phone' => TEST_PHONE, 'code' => '000000'])
            ->assertUnprocessable();

        $this->assertDatabaseCount('users', 0);
    });
});
