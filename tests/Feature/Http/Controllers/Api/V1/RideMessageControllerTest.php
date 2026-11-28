<?php

use App\AlertType;
use App\Events\RideMessageSent;
use App\Models\Ride;
use App\Models\RideMessage;
use App\Models\User;
use App\Notifications\Alert;
use App\RideStatus;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

describe('store', function () {
    it('lets the passenger write to the driver while the ride goes on', function (string $state) {
        Event::fake([RideMessageSent::class]);
        Notification::fake();

        $ride = Ride::factory()->{$state}()->create();

        Sanctum::actingAs($ride->passenger);

        $this->postJson(route('api.v1.rides.messages.store', $ride), ['body' => '  I am near the church.  '])
            ->assertCreated()
            ->assertJsonPath('body', 'I am near the church.')
            ->assertJsonPath('sender_id', $ride->passenger_id)
            ->assertJsonPath('read_at', null);

        expect(RideMessage::sole()->ride_id)->toBe($ride->id);
    })->with(['accepted', 'arrived', 'inProgress']);

    it('lets the driver write to the passenger', function () {
        Event::fake([RideMessageSent::class]);
        Notification::fake();

        $ride = Ride::factory()->accepted()->create();

        Sanctum::actingAs($ride->driver);

        $this->postJson(route('api.v1.rides.messages.store', $ride), ['body' => 'I am coming.'])
            ->assertCreated()
            ->assertJsonPath('sender_id', $ride->driver_id);
    });

    it('tells only the other person, live and by push notification, and keeps the phone numbers out of it', function () {
        Event::fake([RideMessageSent::class]);
        Notification::fake();

        $passenger = User::factory()->create(['name' => 'Maria Santos', 'phone' => '+639181234567']);
        $ride = Ride::factory()->for($passenger, 'passenger')->accepted()->create();

        Sanctum::actingAs($passenger);

        $this->postJson(route('api.v1.rides.messages.store', $ride), ['body' => 'Please wait.'])->assertCreated();

        Event::assertDispatched(RideMessageSent::class, fn (RideMessageSent $event) => $event->recipientId === $ride->driver_id && $event->message->body === 'Please wait.');

        Notification::assertSentTo($ride->driver, Alert::class, fn (Alert $alert) => $alert->type === AlertType::NewMessage && $alert->text === 'Maria: Please wait.');
        Notification::assertNotSentTo($passenger, Alert::class);
    });

    it('does not keep the new message in the alert list', function () {
        expect(AlertType::NewMessage->isStored())->toBeFalse();
    });

    it('needs a message that is not empty and not too long', function (mixed $body) {
        $ride = Ride::factory()->accepted()->create();

        Sanctum::actingAs($ride->passenger);

        $this->postJson(route('api.v1.rides.messages.store', $ride), ['body' => $body])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['body']);

        expect(RideMessage::count())->toBe(0);
    })->with([
        'nothing' => [null],
        'only spaces' => ['   '],
        'too long' => [fn () => str_repeat('a', 501)],
    ]);

    it('is closed before a driver takes the ride', function (string $state) {
        $ride = match ($state) {
            'waiting' => Ride::factory()->create(),
        };

        Sanctum::actingAs($ride->passenger);

        $this->postJson(route('api.v1.rides.messages.store', $ride), ['body' => 'Hello'])->assertForbidden();

        expect(RideMessage::count())->toBe(0);
    })->with(['waiting']);

    it('stays open when the ride was cancelled after a driver took it', function () {
        Event::fake([RideMessageSent::class]);
        Notification::fake();

        $ride = Ride::factory()->accepted()->create();
        $ride->update(['status' => RideStatus::Cancelled]);

        Sanctum::actingAs($ride->driver);

        $this->postJson(route('api.v1.rides.messages.store', $ride), ['body' => 'Sorry, I could not wait.'])->assertCreated();
    });

    it('stays open after the trip, for lost things and thanks, in both directions', function () {
        Event::fake([RideMessageSent::class]);
        Notification::fake();

        $ride = Ride::factory()->completed()->create(['completed_at' => now()->subWeek()]);

        Sanctum::actingAs($ride->passenger);
        $this->postJson(route('api.v1.rides.messages.store', $ride), ['body' => 'I left my bag.'])->assertCreated();

        Sanctum::actingAs($ride->driver);
        $this->postJson(route('api.v1.rides.messages.store', $ride), ['body' => 'I found it.'])->assertCreated();

        expect(RideMessage::count())->toBe(2);
    });

    it('is only for the two people of the ride', function () {
        $ride = Ride::factory()->accepted()->create();

        Sanctum::actingAs(User::factory()->create());

        $this->postJson(route('api.v1.rides.messages.store', $ride), ['body' => 'Hello'])->assertNotFound();
    });

    it('limits how fast messages can be sent', function () {
        Event::fake([RideMessageSent::class]);
        Notification::fake();

        $ride = Ride::factory()->accepted()->create();

        Sanctum::actingAs($ride->passenger);

        foreach (range(1, 30) as $number) {
            $this->postJson(route('api.v1.rides.messages.store', $ride), ['body' => "Message {$number}"])->assertCreated();
        }

        $this->postJson(route('api.v1.rides.messages.store', $ride), ['body' => 'One more'])->assertTooManyRequests();
    });

    it('needs a sign in', function () {
        $this->postJson(route('api.v1.rides.messages.store', Ride::factory()->accepted()->create()), ['body' => 'Hello'])->assertUnauthorized();
    });
});

describe('index', function () {
    it('lists the messages of the chat, oldest first, to both people', function () {
        $ride = Ride::factory()->accepted()->create();
        $first = RideMessage::factory()->create(['ride_id' => $ride->id, 'sender_id' => $ride->passenger_id, 'body' => 'First']);
        $second = RideMessage::factory()->create(['ride_id' => $ride->id, 'sender_id' => $ride->driver_id, 'body' => 'Second']);
        RideMessage::factory()->create();

        Sanctum::actingAs($ride->passenger);

        $this->getJson(route('api.v1.rides.messages.index', $ride))
            ->assertOk()
            ->assertJsonCount(2)
            ->assertJsonPath('0.id', $first->id)
            ->assertJsonPath('1.id', $second->id)
            ->assertJsonPath('1.body', 'Second');

        Sanctum::actingAs($ride->driver);

        $this->getJson(route('api.v1.rides.messages.index', $ride))->assertOk()->assertJsonCount(2);
    });

    it('can be read after the trip, but not before a driver takes the ride', function () {
        $done = Ride::factory()->completed()->create();
        RideMessage::factory()->create(['ride_id' => $done->id, 'sender_id' => $done->passenger_id]);

        Sanctum::actingAs($done->passenger);

        $this->getJson(route('api.v1.rides.messages.index', $done))->assertOk()->assertJsonCount(1);

        $waiting = Ride::factory()->create();

        Sanctum::actingAs($waiting->passenger);

        $this->getJson(route('api.v1.rides.messages.index', $waiting))->assertForbidden();
    });

    it('only lists the messages after the one that was asked for', function () {
        $ride = Ride::factory()->accepted()->create();
        $old = RideMessage::factory()->create(['ride_id' => $ride->id, 'sender_id' => $ride->passenger_id]);
        $new = RideMessage::factory()->create(['ride_id' => $ride->id, 'sender_id' => $ride->driver_id]);

        Sanctum::actingAs($ride->passenger);

        $this->getJson(route('api.v1.rides.messages.index', ['ride' => $ride, 'after' => $old->id]))
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.id', $new->id);
    });

    it('shows the last 100 messages of a long chat', function () {
        $ride = Ride::factory()->accepted()->create();
        RideMessage::factory()->count(105)->create(['ride_id' => $ride->id, 'sender_id' => $ride->passenger_id]);

        Sanctum::actingAs($ride->passenger);

        $response = $this->getJson(route('api.v1.rides.messages.index', $ride))->assertOk()->assertJsonCount(100);

        expect($response->json('99.id'))->toBe(RideMessage::max('id'));
    });

    it('marks what the other person wrote as read, and not what the reader wrote', function () {
        $ride = Ride::factory()->accepted()->create();
        $theirs = RideMessage::factory()->create(['ride_id' => $ride->id, 'sender_id' => $ride->driver_id]);
        $mine = RideMessage::factory()->create(['ride_id' => $ride->id, 'sender_id' => $ride->passenger_id]);

        Sanctum::actingAs($ride->passenger);

        $this->getJson(route('api.v1.rides.messages.index', $ride))->assertOk();

        expect($theirs->refresh()->read_at)->not->toBeNull();
        expect($mine->refresh()->read_at)->toBeNull();
    });

    it('is only for the two people of the ride', function () {
        $ride = Ride::factory()->accepted()->create();

        Sanctum::actingAs(User::factory()->create());

        $this->getJson(route('api.v1.rides.messages.index', $ride))->assertNotFound();
    });
});

describe('one conversation for two people', function () {
    it('shows what they wrote on all their rides, oldest first, and marks it as read', function () {
        $before = Ride::factory()->completed()->create();
        $now = Ride::factory()->for($before->passenger, 'passenger')->accepted($before->driver)->create();
        $old = RideMessage::factory()->create(['ride_id' => $before->id, 'sender_id' => $before->driver_id]);
        $new = RideMessage::factory()->create(['ride_id' => $now->id, 'sender_id' => $now->passenger_id]);

        Sanctum::actingAs($now->passenger);

        $this->getJson(route('api.v1.rides.messages.index', $now))->assertOk()->assertJsonPath('*.id', [$old->id, $new->id]);

        expect($old->fresh()->read_at)->not->toBeNull();
    });

    it('keeps out the chats with other people', function () {
        $ride = Ride::factory()->completed()->create();
        $mine = RideMessage::factory()->create(['ride_id' => $ride->id, 'sender_id' => $ride->passenger_id]);
        // The same passenger with another driver, the same driver with another passenger, and a driver who gave this pickup up.
        $otherDriver = Ride::factory()->for($ride->passenger, 'passenger')->completed()->create();
        RideMessage::factory()->create(['ride_id' => $otherDriver->id, 'sender_id' => $otherDriver->passenger_id]);
        $otherPassenger = Ride::factory()->completed($ride->driver)->create();
        RideMessage::factory()->create(['ride_id' => $otherPassenger->id, 'sender_id' => $otherPassenger->passenger_id]);
        RideMessage::factory()->create(['ride_id' => $ride->id, 'sender_id' => User::factory()->driver()->create()->id]);

        Sanctum::actingAs($ride->driver);

        $this->getJson(route('api.v1.rides.messages.index', $ride))->assertOk()->assertJsonPath('*.id', [$mine->id]);
    });
});

describe('unread messages on the ride', function () {
    it('counts what the other person wrote and the reader did not read yet', function () {
        $ride = Ride::factory()->accepted()->create();
        RideMessage::factory()->count(2)->create(['ride_id' => $ride->id, 'sender_id' => $ride->driver_id]);
        RideMessage::factory()->create(['ride_id' => $ride->id, 'sender_id' => $ride->passenger_id]);

        Sanctum::actingAs($ride->passenger);

        $this->getJson(route('api.v1.rides.show', $ride))->assertOk()->assertJsonPath('unread_messages', 2);

        Sanctum::actingAs($ride->driver);

        $this->getJson(route('api.v1.rides.show', $ride))->assertOk()->assertJsonPath('unread_messages', 1);
    });

    it('goes back to zero once the chat was read', function () {
        $ride = Ride::factory()->accepted()->create();
        RideMessage::factory()->create(['ride_id' => $ride->id, 'sender_id' => $ride->driver_id]);

        Sanctum::actingAs($ride->passenger);

        $this->getJson(route('api.v1.rides.messages.index', $ride))->assertOk();
        $this->getJson(route('api.v1.rides.show', $ride))->assertOk()->assertJsonPath('unread_messages', 0);
    });

    it('is not shown when the chat is closed', function () {
        $ride = Ride::factory()->completed()->create();

        Sanctum::actingAs($ride->passenger);

        $this->getJson(route('api.v1.rides.show', $ride))->assertOk()->assertJsonMissingPath('unread_messages');
    });
});

describe('photos', function () {
    it('lets a person send a photo, with or without words', function (?string $body) {
        Storage::fake();
        Event::fake([RideMessageSent::class]);
        Notification::fake();

        $ride = Ride::factory()->accepted()->create();

        Sanctum::actingAs($ride->passenger);

        $response = $this->post(route('api.v1.rides.messages.store', $ride), array_filter(['body' => $body, 'image' => UploadedFile::fake()->image('spot.jpg')]), ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('body', $body);

        $message = RideMessage::sole();

        expect($message->image_path)->not->toBeNull();
        Storage::assertExists($message->image_path);
        expect($response->json('image_url'))->toStartWith('/api/v1/rides/'.$ride->id.'/messages/'.$message->id.'/image?');
    })->with(['only a photo' => [null], 'a photo and words' => ['I am by the blue gate.']]);

    it('says that a photo was sent in the notification', function () {
        Storage::fake();
        Event::fake([RideMessageSent::class]);
        Notification::fake();

        $passenger = User::factory()->create(['name' => 'Maria Santos']);
        $ride = Ride::factory()->for($passenger, 'passenger')->accepted()->create();

        Sanctum::actingAs($passenger);

        $this->post(route('api.v1.rides.messages.store', $ride), ['image' => UploadedFile::fake()->image('spot.jpg')], ['Accept' => 'application/json'])->assertCreated();

        Notification::assertSentTo($ride->driver, Alert::class, fn (Alert $alert) => $alert->text === 'Maria: Sent a photo');
    });

    it('needs a photo or words, and only a real photo that is not too big', function (array $data) {
        Storage::fake();

        $ride = Ride::factory()->accepted()->create();

        Sanctum::actingAs($ride->passenger);

        $this->post(route('api.v1.rides.messages.store', $ride), $data, ['Accept' => 'application/json'])->assertUnprocessable();

        expect(RideMessage::count())->toBe(0);
    })->with([
        'nothing' => [[]],
        'a document' => [fn () => ['image' => UploadedFile::fake()->create('paper.pdf', 100, 'application/pdf')]],
        'too big' => [fn () => ['image' => UploadedFile::fake()->image('big.jpg')->size(6000)]],
    ]);

    it('shows the photo with the signed link to the two people of the ride, and to nobody else', function () {
        Storage::fake();
        Event::fake([RideMessageSent::class]);
        Notification::fake();

        $ride = Ride::factory()->accepted()->create();

        Sanctum::actingAs($ride->passenger);

        $url = $this->post(route('api.v1.rides.messages.store', $ride), ['image' => UploadedFile::fake()->image('spot.jpg')], ['Accept' => 'application/json'])->json('image_url');

        // The link works without a sign in token, as the picture on the phone does.
        auth()->forgetGuards();
        $this->get($url)->assertOk();

        $this->get(preg_replace('/signature=[^&]+/', 'signature=wrong', $url))->assertForbidden();
        $this->get('/api/v1/rides/'.$ride->id.'/messages/'.RideMessage::sole()->id.'/image')->assertForbidden();
    });

    it('lists the link of a photo in the chat', function () {
        Storage::fake();

        $ride = Ride::factory()->accepted()->create();
        RideMessage::factory()->create(['ride_id' => $ride->id, 'sender_id' => $ride->driver_id, 'body' => null, 'image_path' => 'ride-message-photos/'.$ride->id.'/a.jpg']);
        RideMessage::factory()->create(['ride_id' => $ride->id, 'sender_id' => $ride->driver_id, 'body' => 'Words only']);

        Sanctum::actingAs($ride->passenger);

        $this->getJson(route('api.v1.rides.messages.index', $ride))
            ->assertOk()
            ->assertJsonPath('0.body', null)
            ->assertJsonPath('0.image_url', fn (string $url) => str_contains($url, '/image?'))
            ->assertJsonPath('1.image_url', null);
    });

    it('is deleted with the account of the person who sent it', function () {
        Storage::fake();
        Storage::put('ride-message-photos/1/a.jpg', 'x');

        $ride = Ride::factory()->completed()->create();
        $message = RideMessage::factory()->create(['ride_id' => $ride->id, 'sender_id' => $ride->driver_id, 'body' => null, 'image_path' => 'ride-message-photos/1/a.jpg']);

        Sanctum::actingAs($ride->driver);

        $this->deleteJson(route('api.v1.me.destroy'))->assertNoContent();

        Storage::assertMissing('ride-message-photos/1/a.jpg');
        expect(RideMessage::find($message->id))->toBeNull();
    });
});
