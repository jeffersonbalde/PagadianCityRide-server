<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;
use Laravel\Sanctum\Sanctum;

/**
 * The tests run with the null broadcaster, which never checks a channel. These
 * tests switch to the real Reverb driver (with fake keys) and load the channel
 * rules again, so the rules are what is tested.
 */
beforeEach(function () {
    config([
        'broadcasting.default' => 'reverb',
        'broadcasting.connections.reverb.key' => 'test-key',
        'broadcasting.connections.reverb.secret' => 'test-secret',
        'broadcasting.connections.reverb.app_id' => 'test-app',
        'broadcasting.connections.reverb.options.host' => 'localhost',
        'broadcasting.connections.reverb.options.port' => 8080,
        'broadcasting.connections.reverb.options.scheme' => 'http',
        'broadcasting.connections.reverb.options.useTLS' => false,
    ]);

    Broadcast::purge();

    require base_path('routes/channels.php');
});

function authorizeChannel(User $user, string $channel)
{
    Sanctum::actingAs($user);

    return test()->postJson('/api/v1/broadcasting/auth', [
        'channel_name' => $channel,
        'socket_id' => '1234.5678',
    ]);
}

it('lets a person listen to their own private channel', function (string $role) {
    $user = User::factory()->create(['role' => $role]);

    authorizeChannel($user, 'private-App.Models.User.'.$user->id)
        ->assertOk()
        ->assertJsonStructure(['auth']);
})->with(['passenger', 'driver']);

it('signs the answer with the app key', function () {
    $user = User::factory()->create();

    authorizeChannel($user, 'private-App.Models.User.'.$user->id)
        ->assertOk()
        ->assertJson(fn ($json) => $json->where('auth', fn (string $auth) => str_starts_with($auth, 'test-key:'))->etc());
});

it('refuses the private channel of another person', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();

    authorizeChannel($user, 'private-App.Models.User.'.$other->id)->assertForbidden();
});

it('refuses a channel that the app does not know', function () {
    $user = User::factory()->create();

    authorizeChannel($user, 'private-rides.1')->assertForbidden();
});

it('refuses a person who is not signed in', function () {
    $user = User::factory()->create();

    $this->postJson('/api/v1/broadcasting/auth', [
        'channel_name' => 'private-App.Models.User.'.$user->id,
        'socket_id' => '1234.5678',
    ])->assertUnauthorized();
});
