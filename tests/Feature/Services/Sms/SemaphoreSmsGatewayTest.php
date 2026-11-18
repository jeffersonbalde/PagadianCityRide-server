<?php

use App\Exceptions\SmsDeliveryFailed;
use App\Services\Sms\SemaphoreSmsGateway;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config([
        'services.semaphore.key' => 'test-key',
        'services.semaphore.sender_name' => null,
    ]);

    Http::preventStrayRequests();
});

it('posts the number without the plus sign and the message to the priority route', function () {
    config(['services.semaphore.sender_name' => 'PagadianRide']);

    Http::fake([
        'api.semaphore.co/api/v4/priority' => Http::response([['status' => 'Queued']]),
    ]);

    (new SemaphoreSmsGateway)->send('+639171234567', 'Your code is 482915.');

    Http::assertSentCount(1);
    Http::assertSent(fn (Request $request) => $request->method() === 'POST'
        && $request->url() === 'https://api.semaphore.co/api/v4/priority'
        && $request['apikey'] === 'test-key'
        && $request['number'] === '639171234567'
        && $request['message'] === 'Your code is 482915.'
        && $request['sendername'] === 'PagadianRide');
});

it('leaves out the sender name when none is configured', function () {
    Http::fake([
        'api.semaphore.co/api/v4/priority' => Http::response([['status' => 'Queued']]),
    ]);

    (new SemaphoreSmsGateway)->send('+639171234567', 'Hello');

    Http::assertSent(fn (Request $request) => ! array_key_exists('sendername', $request->data()));
});

it('throws and does not retry when Semaphore answers with an error', function (int $status) {
    Http::fake([
        'api.semaphore.co/api/v4/priority' => Http::response(['error' => 'nope'], $status),
    ]);

    expect(fn () => (new SemaphoreSmsGateway)->send('+639171234567', 'Hello'))
        ->toThrow(SmsDeliveryFailed::class);

    Http::assertSentCount(1);
})->with([
    'unauthorized' => 401,
    'rate limited' => 429,
    'server error' => 500,
]);

it('throws when Semaphore cannot be reached', function () {
    Http::fake([
        'api.semaphore.co/*' => Http::failedConnection(),
    ]);

    expect(fn () => (new SemaphoreSmsGateway)->send('+639171234567', 'Hello'))
        ->toThrow(SmsDeliveryFailed::class);
});
