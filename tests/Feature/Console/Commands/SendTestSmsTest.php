<?php

use App\Services\Sms\SmsGateway;
use Illuminate\Support\Facades\Http;

it('sends one test text to the number, written in any common way', function () {
    config(['services.sms.driver' => 'array']);
    app()->forgetInstance(SmsGateway::class);

    $this->artisan('sms:test', ['phone' => '0917 123 4567'])
        ->expectsOutputToContain('Handed to the "array" SMS provider for +639171234567.')
        ->assertSuccessful();

    expect(sms()->sent())->toHaveCount(1);
    expect(sms()->sent()[0]['phone'])->toBe('+639171234567');
    expect(sms()->sent()[0]['message'])->toContain('this is a test text');
});

it('says so when the provider is the log, because no phone gets the text', function () {
    config(['services.sms.driver' => 'log']);
    app()->forgetInstance(SmsGateway::class);

    $this->artisan('sms:test', ['phone' => '09171234567'])
        ->expectsOutputToContain('no phone gets it')
        ->assertSuccessful();
});

it('refuses a number that is not a Philippine mobile number', function () {
    $this->artisan('sms:test', ['phone' => '12345'])
        ->expectsOutputToContain('not a Philippine mobile number')
        ->assertFailed();
});

it('fails and tells why when the provider does not take the text', function () {
    config(['services.sms.driver' => 'android', 'services.android_sms.username' => 'x', 'services.android_sms.password' => 'y']);
    app()->forgetInstance(SmsGateway::class);
    Http::preventStrayRequests();
    Http::fake(['api.sms-gate.app/*' => Http::response(['message' => 'Unauthorized'], 401)]);

    $this->artisan('sms:test', ['phone' => '09171234567'])
        ->expectsOutputToContain('did not take the text')
        ->assertFailed();
});
