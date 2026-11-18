<?php

use App\Models\PhoneVerificationCode;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;

it('returns 202 and texts a code to the phone number', function () {
    $response = $this->postJson(route('api.v1.auth.login-code.store'), ['phone' => '0917 123 4567']);

    $response->assertAccepted()->assertExactJson(['message' => 'We sent a code to your phone.']);

    expect(sms()->sent())->toHaveCount(1);
    expect(sms()->sent()[0]['phone'])->toBe('+639171234567');
    expect(sms()->sent()[0]['message'])->toContain('PagadianCityRide')->toContain('expires in 5 minutes');

    $code = sms()->lastCodeFor('+639171234567');
    $record = PhoneVerificationCode::sole();

    expect($record->phone)->toBe('+639171234567');
    expect($record->expires_at->isFuture())->toBeTrue();
    expect($record->getRawOriginal('code'))->not->toBe($code);
    expect(Hash::check($code, $record->getRawOriginal('code')))->toBeTrue();
});

it('accepts the usual ways of typing a Philippine mobile number', function (string $phone) {
    $this->postJson(route('api.v1.auth.login-code.store'), ['phone' => $phone])->assertAccepted();

    expect(sms()->sent()[0]['phone'])->toBe('+639171234567');
})->with([
    'local format' => '09171234567',
    'country code without plus' => '639171234567',
    'country code with plus and spaces' => '+63 917 123 4567',
    'without the leading zero' => '9171234567',
]);

it('returns 422 when the phone number is not a Philippine mobile number', function (string $phone) {
    $this->postJson(route('api.v1.auth.login-code.store'), ['phone' => $phone])
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'phone' => 'Enter a valid Philippine mobile number, for example 0917 123 4567.',
        ]);

    expect(sms()->sent())->toBeEmpty();
    $this->assertDatabaseCount('phone_verification_codes', 0);
})->with([
    'letters' => 'not a number',
    'too short' => '0917123',
    'landline' => '028123456',
    'other country' => '+14155550100',
]);

it('returns 422 when the phone number is missing', function () {
    $this->postJson(route('api.v1.auth.login-code.store'), [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['phone']);
});

it('replaces the earlier code when a new one is requested', function () {
    $earlierCode = PhoneVerificationCode::factory()->create(['phone' => '+639171234567']);

    $this->postJson(route('api.v1.auth.login-code.store'), ['phone' => '09171234567'])->assertAccepted();

    $this->assertModelMissing($earlierCode);
    $this->assertDatabaseCount('phone_verification_codes', 1);
});

it('does not touch the codes of other phone numbers', function () {
    $otherCode = PhoneVerificationCode::factory()->create(['phone' => '+639181234567']);

    $this->postJson(route('api.v1.auth.login-code.store'), ['phone' => '09171234567'])->assertAccepted();

    $this->assertModelExists($otherCode);
});

it('returns 429 when the same phone number asks twice within a minute', function () {
    $this->postJson(route('api.v1.auth.login-code.store'), ['phone' => '09171234567'])->assertAccepted();

    $this->postJson(route('api.v1.auth.login-code.store'), ['phone' => '+63 917 123 4567'])->assertTooManyRequests();

    expect(sms()->sent())->toHaveCount(1);
});

it('returns 503 and keeps no code when the text cannot be sent', function () {
    config(['services.sms.driver' => 'semaphore', 'services.semaphore.key' => 'test-key']);
    Http::preventStrayRequests();
    Http::fake(['api.semaphore.co/api/v4/priority' => Http::response(['error' => 'down'], 500)]);

    $this->postJson(route('api.v1.auth.login-code.store'), ['phone' => '09171234567'])
        ->assertServiceUnavailable()
        ->assertExactJson(['message' => 'We could not send the text message. Please try again in a moment.']);

    $this->assertDatabaseCount('phone_verification_codes', 0);
});
