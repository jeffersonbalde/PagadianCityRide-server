<?php

use App\Models\DriverProfile;
use App\Models\User;
use App\Services\Push\ArrayPushGateway;
use App\Services\Push\PushGateway;
use App\Services\Sms\ArraySmsGateway;
use App\Services\Sms\SmsGateway;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(LazilyRefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

/**
 * An approved driver who is online at the given point (Balangasan by default).
 */
function onlineDriver(float $latitude = 7.8231, float $longitude = 123.4402): User
{
    $driver = User::factory()->driver()->create();

    DriverProfile::factory()->for($driver)->onlineAt($latitude, $longitude)->create();

    return $driver;
}

/**
 * The body of a request for a ride, with the given parts replaced.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function rideRequestPayload(array $overrides = []): array
{
    return array_replace_recursive([
        'pickup' => ['latitude' => 7.8231, 'longitude' => 123.4402, 'label' => 'Balangasan'],
        'destination' => ['latitude' => 7.8249, 'longitude' => 123.4389, 'label' => 'City Hall'],
        'passengers_count' => 2,
        'note' => 'Red shirt, near the church',
        'distance_meters' => 1800,
        'duration_seconds' => 420,
    ], $overrides);
}

/**
 * The SMS gateway used in tests. It keeps the texts in memory instead of sending them.
 */
function sms(): ArraySmsGateway
{
    return app(SmsGateway::class);
}

/**
 * The push gateway used in tests. It keeps the notifications in memory instead of sending them.
 */
function push(): ArrayPushGateway
{
    return app(PushGateway::class);
}
