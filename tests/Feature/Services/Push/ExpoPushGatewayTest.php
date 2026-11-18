<?php

use App\Services\Push\ExpoPushGateway;
use App\Services\Push\PushGateway;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Http;

const EXPO_URL = 'https://exp.host/--/api/v2/push/send';

function expoMessages(int $count): array
{
    return array_map(
        fn (int $number): array => ['to' => "ExponentPushToken[token-{$number}]", 'title' => 'Title', 'body' => 'Body', 'data' => ['n' => $number]],
        range(1, $count),
    );
}

beforeEach(function () {
    Http::preventStrayRequests();
});

it('posts the messages with a sound and high priority', function () {
    Http::fake([EXPO_URL => Http::response(['data' => [['status' => 'ok', 'id' => 'a']]])]);

    $unregistered = (new ExpoPushGateway(EXPO_URL))->send(expoMessages(1));

    expect($unregistered)->toBe([]);

    Http::assertSentCount(1);
    Http::assertSent(fn (Request $request) => $request->method() === 'POST'
        && $request->url() === EXPO_URL
        && $request->data() === [[
            'to' => 'ExponentPushToken[token-1]',
            'title' => 'Title',
            'body' => 'Body',
            'data' => ['n' => 1],
            'sound' => 'default',
            'priority' => 'high',
        ]]
        && ! $request->hasHeader('Authorization'));
});

it('sends the access token when there is one', function () {
    Http::fake([EXPO_URL => Http::response(['data' => [['status' => 'ok']]])]);

    (new ExpoPushGateway(EXPO_URL, 'secret-token'))->send(expoMessages(1));

    Http::assertSent(fn (Request $request) => $request->header('Authorization') === ['Bearer secret-token']);
});

it('sends at most 100 messages in one request', function () {
    Http::fake([EXPO_URL => Http::response(['data' => []])]);

    (new ExpoPushGateway(EXPO_URL))->send(expoMessages(150));

    Http::assertSentCount(2);
    Http::assertSent(fn (Request $request) => count($request->data()) === 100);
    Http::assertSent(fn (Request $request) => count($request->data()) === 50 && $request->data()[0]['to'] === 'ExponentPushToken[token-101]');
});

it('does nothing when there are no messages', function () {
    expect((new ExpoPushGateway(EXPO_URL))->send([]))->toBe([]);

    Http::assertNothingSent();
});

it('returns the tokens that Expo does not know any more', function () {
    Http::fake([EXPO_URL => Http::response(['data' => [
        ['status' => 'ok', 'id' => 'a'],
        ['status' => 'error', 'message' => 'gone', 'details' => ['error' => 'DeviceNotRegistered']],
        ['status' => 'error', 'message' => 'too big', 'details' => ['error' => 'MessageTooBig']],
    ]])]);

    $unregistered = (new ExpoPushGateway(EXPO_URL))->send(expoMessages(3));

    expect($unregistered)->toBe(['ExponentPushToken[token-2]']);
});

it('reports a failure and does not throw, so the ride is never held back by a push', function (int $status) {
    Exceptions::fake();
    Http::fake([EXPO_URL => Http::response(['errors' => []], $status)]);

    expect((new ExpoPushGateway(EXPO_URL))->send(expoMessages(2)))->toBe([]);

    Http::assertSentCount(1);
    Exceptions::assertReported(RequestException::class);
})->with([
    'rate limited' => 429,
    'server error' => 500,
]);

it('reports it and does not throw when Expo cannot be reached', function () {
    Exceptions::fake();
    Http::fake(fn () => throw new ConnectionException('timeout'));

    expect((new ExpoPushGateway(EXPO_URL))->send(expoMessages(1)))->toBe([]);

    Exceptions::assertReported(ConnectionException::class);
});

it('is the gateway of the app when the push driver is expo', function () {
    config(['services.push.driver' => 'expo', 'services.expo.access_token' => 'abc']);
    app()->forgetInstance(PushGateway::class);

    expect(app(PushGateway::class))->toBeInstanceOf(ExpoPushGateway::class);
});
