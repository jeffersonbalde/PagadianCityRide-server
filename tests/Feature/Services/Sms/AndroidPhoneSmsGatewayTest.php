<?php

use App\Exceptions\SmsDeliveryFailed;
use App\Services\Sms\AndroidPhoneSmsGateway;
use App\Services\Sms\SmsGateway;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config([
        'services.android_sms.username' => 'ABC123',
        'services.android_sms.password' => 'secret-pass',
    ]);

    Http::preventStrayRequests();
});

it('hands the text to the phone with the number, a short life and a high priority', function () {
    Http::fake([
        'api.sms-gate.app/3rdparty/v1/messages' => Http::response(['id' => 'm1', 'state' => 'Pending'], 202),
    ]);

    (new AndroidPhoneSmsGateway)->send('+639171234567', 'Your code is 482915.');

    Http::assertSentCount(1);
    Http::assertSent(fn (Request $request) => $request->method() === 'POST'
        && $request->url() === 'https://api.sms-gate.app/3rdparty/v1/messages'
        && $request->hasHeader('Authorization', 'Basic '.base64_encode('ABC123:secret-pass'))
        && $request['textMessage'] === ['text' => 'Your code is 482915.']
        && $request['phoneNumbers'] === ['+639171234567']
        && $request['ttl'] === 300
        && $request['priority'] === 100);
});

it('uses the address and the life of a text that are set in the settings', function () {
    config([
        'services.android_sms.base_url' => 'https://sms.example.test/3rdparty/v1',
        'services.android_sms.ttl_seconds' => 120,
    ]);

    Http::fake(['sms.example.test/*' => Http::response(['state' => 'Pending'], 202)]);

    (new AndroidPhoneSmsGateway)->send('+639171234567', 'Hello');

    Http::assertSent(fn (Request $request) => $request->url() === 'https://sms.example.test/3rdparty/v1/messages' && $request['ttl'] === 120);
});

it('throws and does not retry when the gateway answers with an error', function (int $status) {
    Http::fake([
        'api.sms-gate.app/*' => Http::response(['message' => 'nope'], $status),
    ]);

    expect(fn () => (new AndroidPhoneSmsGateway)->send('+639171234567', 'Hello'))
        ->toThrow(SmsDeliveryFailed::class);

    Http::assertSentCount(1);
})->with([
    'wrong username or password' => 401,
    'queue is full' => 503,
    'server error' => 500,
]);

it('throws when the gateway cannot be reached', function () {
    Http::fake(['api.sms-gate.app/*' => Http::failedConnection()]);

    expect(fn () => (new AndroidPhoneSmsGateway)->send('+639171234567', 'Hello'))
        ->toThrow(SmsDeliveryFailed::class);
});

it('is the SMS provider when SMS_DRIVER is android', function () {
    config(['services.sms.driver' => 'android']);
    app()->forgetInstance(SmsGateway::class);

    expect(app(SmsGateway::class))->toBeInstanceOf(AndroidPhoneSmsGateway::class);
});
