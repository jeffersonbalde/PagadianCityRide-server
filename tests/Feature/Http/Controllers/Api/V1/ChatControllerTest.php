<?php

use App\Models\DriverProfile;
use App\Models\Ride;
use App\Models\RideMessage;
use App\Models\User;
use App\RideStatus;
use Laravel\Sanctum\Sanctum;

it('lists the chats of the passenger with the driver, the last message and what was not read', function () {
    $profile = DriverProfile::factory()->approved()->create(['tricycle_number' => '#214', 'plate_number' => 'ABC 1234']);
    $profile->user->update(['name' => 'Ramil']);
    $ride = Ride::factory()->completed($profile->user)->create();

    RideMessage::factory()->create(['ride_id' => $ride->id, 'sender_id' => $ride->passenger_id, 'body' => 'I am by the gate.', 'created_at' => now()->subMinutes(5)]);
    RideMessage::factory()->count(2)->create(['ride_id' => $ride->id, 'sender_id' => $ride->driver_id, 'body' => 'Coming now.']);

    Sanctum::actingAs($ride->passenger);

    $this->getJson(route('api.v1.chats.index'))
        ->assertOk()
        ->assertJsonCount(1)
        ->assertJsonPath('0.ride_id', $ride->id)
        ->assertJsonPath('0.name', 'Ramil')
        ->assertJsonPath('0.subtitle', '#214 · ABC 1234')
        ->assertJsonPath('0.unread_count', 2)
        ->assertJsonPath('0.open', true)
        ->assertJsonPath('0.ongoing', false)
        ->assertJsonPath('0.last_message.body', 'Coming now.')
        ->assertJsonPath('0.last_message.mine', false)
        ->assertJsonPath('0.last_message.has_photo', false)
        ->assertJsonPath('0.photo_url', fn (?string $url) => $url === null || str_contains($url, '/driver/photo?'));
});

it('lists the chats of the driver with the passenger and the pickup', function () {
    $passenger = User::factory()->create(['name' => 'Maria Santos']);
    $ride = Ride::factory()->for($passenger, 'passenger')->completed()->create(['pickup_label' => 'Balangasan']);
    RideMessage::factory()->create(['ride_id' => $ride->id, 'sender_id' => $ride->driver_id, 'body' => 'On my way.']);

    Sanctum::actingAs($ride->driver);

    $this->getJson(route('api.v1.chats.index'))
        ->assertOk()
        ->assertJsonPath('0.name', 'Maria Santos')
        ->assertJsonPath('0.subtitle', 'Balangasan')
        ->assertJsonPath('0.photo_url', null)
        ->assertJsonPath('0.unread_count', 0)
        ->assertJsonPath('0.last_message.mine', true);
});

it('says that the last message is a photo', function () {
    $ride = Ride::factory()->accepted()->create();
    RideMessage::factory()->create(['ride_id' => $ride->id, 'sender_id' => $ride->driver_id, 'body' => null, 'image_path' => 'ride-message-photos/1/a.jpg']);

    Sanctum::actingAs($ride->passenger);

    $this->getJson(route('api.v1.chats.index'))
        ->assertOk()
        ->assertJsonPath('0.last_message.has_photo', true)
        ->assertJsonPath('0.last_message.body', null);
});

it('shows a ride that is going on even before anyone wrote, and marks it as open', function () {
    $ride = Ride::factory()->accepted()->create();

    Sanctum::actingAs($ride->passenger);

    $this->getJson(route('api.v1.chats.index'))
        ->assertOk()
        ->assertJsonCount(1)
        ->assertJsonPath('0.open', true)
        ->assertJsonPath('0.ongoing', true)
        ->assertJsonPath('0.last_message', null)
        ->assertJsonPath('0.unread_count', 0);
});

it('leaves out rides without a driver, finished rides without messages and the chats of other people', function () {
    $passenger = User::factory()->create();

    Ride::factory()->for($passenger, 'passenger')->create();
    Ride::factory()->for($passenger, 'passenger')->completed()->create();
    RideMessage::factory()->create();

    Sanctum::actingAs($passenger);

    $this->getJson(route('api.v1.chats.index'))->assertOk()->assertJsonCount(0);
});

it('puts the chat with the newest message first', function () {
    $passenger = User::factory()->create();
    $older = Ride::factory()->for($passenger, 'passenger')->completed()->create();
    $newer = Ride::factory()->for($passenger, 'passenger')->completed()->create();

    RideMessage::factory()->create(['ride_id' => $newer->id, 'sender_id' => $newer->driver_id, 'created_at' => now()->subHour()]);
    RideMessage::factory()->create(['ride_id' => $older->id, 'sender_id' => $older->driver_id, 'created_at' => now()->subMinute()]);

    Sanctum::actingAs($passenger);

    $this->getJson(route('api.v1.chats.index'))
        ->assertOk()
        ->assertJsonPath('0.ride_id', $older->id)
        ->assertJsonPath('1.ride_id', $newer->id);
});

it('needs a sign in and is not for admins', function () {
    $this->getJson(route('api.v1.chats.index'))->assertUnauthorized();

    Sanctum::actingAs(User::factory()->admin()->create());

    $this->getJson(route('api.v1.chats.index'))->assertForbidden();
});

it('keeps the chat of a cancelled ride open, but not marked as going on', function () {
    $ride = Ride::factory()->accepted()->create();
    $ride->update(['status' => RideStatus::Cancelled]);
    RideMessage::factory()->create(['ride_id' => $ride->id, 'sender_id' => $ride->driver_id]);

    Sanctum::actingAs($ride->passenger);

    $this->getJson(route('api.v1.chats.index'))
        ->assertOk()
        ->assertJsonPath('0.open', true)
        ->assertJsonPath('0.ongoing', false);
});
