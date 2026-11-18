<?php

use App\DocumentStatus;
use App\DocumentType;
use App\Models\DriverProfile;
use App\Models\Ride;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('shows the passenger who is coming, what was verified and what others said', function () {
    $profile = DriverProfile::factory()->approved()->create(['tricycle_number' => '#214', 'plate_number' => 'ABC 1234', 'franchise_number' => '0214']);
    $driver = $profile->user;
    $driver->update(['name' => 'Ramil Dela Cruz', 'phone' => '+639175550214']);

    $ride = Ride::factory()->accepted($driver)->create();
    $maria = User::factory()->create(['name' => 'Maria Santos']);
    Ride::factory()->completed($driver)->rated(5, 'Very kind and on time.')->create(['passenger_id' => $maria->id, 'completed_at' => now()->subDay()]);
    Ride::factory()->completed($driver)->rated(4)->create();

    Sanctum::actingAs($ride->passenger);

    $response = $this->getJson(route('api.v1.rides.driver.show', $ride))
        ->assertOk()
        ->assertJsonPath('name', 'Ramil Dela Cruz')
        ->assertJsonPath('tricycle_number', '#214')
        ->assertJsonPath('plate_number', 'ABC 1234')
        ->assertJsonPath('franchise_number', '0214')
        ->assertJsonPath('rating_average', 4.5)
        ->assertJsonPath('completed_trips_count', 2)
        ->assertJsonPath('verified', true)
        ->assertJsonCount(4, 'documents')
        ->assertJsonPath('documents.0', ['type' => 'photo', 'verified' => true])
        ->assertJsonCount(1, 'reviews')
        ->assertJsonPath('reviews.0.rating', 5)
        ->assertJsonPath('reviews.0.review', 'Very kind and on time.')
        ->assertJsonPath('reviews.0.reviewer', 'Maria');

    // The contact number is part of the profile, as the two shared a ride. The full name of a reviewer is not given away.
    $response->assertJsonPath('phone', '+639175550214')->assertJsonPath('trips_together', 0);
    expect($response->getContent())->not->toContain('Santos');
});

it('shows which documents are not verified yet', function () {
    $profile = DriverProfile::factory()->submitted()->create();
    $profile->documents()->where('type', DocumentType::License)->update(['status' => DocumentStatus::Verified]);

    $ride = Ride::factory()->accepted($profile->user)->create();

    Sanctum::actingAs($ride->passenger);

    $this->getJson(route('api.v1.rides.driver.show', $ride))
        ->assertOk()
        ->assertJsonPath('verified', false)
        ->assertJsonPath('documents.0.verified', false)
        ->assertJsonPath('documents.1', ['type' => 'license', 'verified' => true]);
});

it('has no rating or reviews for a new driver', function () {
    $ride = Ride::factory()->accepted()->create();

    Sanctum::actingAs($ride->passenger);

    $this->getJson(route('api.v1.rides.driver.show', $ride))
        ->assertOk()
        ->assertJsonPath('rating_average', null)
        ->assertJsonPath('completed_trips_count', 0)
        ->assertJsonPath('reviews', []);
});

it('only lists the ten newest reviews', function () {
    $driver = User::factory()->driver()->create();
    $ride = Ride::factory()->accepted($driver)->create();

    foreach (range(1, 12) as $day) {
        Ride::factory()->completed($driver)->rated(5, "Review {$day}")->create(['completed_at' => now()->subDays($day)]);
    }

    Sanctum::actingAs($ride->passenger);

    $this->getJson(route('api.v1.rides.driver.show', $ride))
        ->assertOk()
        ->assertJsonCount(10, 'reviews')
        ->assertJsonPath('reviews.0.review', 'Review 1');
});

it('is closed before a driver takes the ride', function () {
    $ride = Ride::factory()->create();

    Sanctum::actingAs($ride->passenger);

    $this->getJson(route('api.v1.rides.driver.show', $ride))->assertForbidden();
});

it('is only for the passenger of the ride', function () {
    $ride = Ride::factory()->accepted()->create();

    Sanctum::actingAs(User::factory()->create());
    $this->getJson(route('api.v1.rides.driver.show', $ride))->assertNotFound();

    Sanctum::actingAs($ride->driver);
    $this->getJson(route('api.v1.rides.driver.show', $ride))->assertForbidden();
});

describe('photo of the driver', function () {
    it('gives the passenger a link to the photo from the registration, on the ride and on the profile', function () {
        $profile = DriverProfile::factory()->approved()->create();
        $ride = Ride::factory()->accepted($profile->user)->create();

        Sanctum::actingAs($ride->passenger);

        $onRide = $this->getJson(route('api.v1.rides.show', $ride))->assertOk()->json('driver.photo_url');
        $onProfile = $this->getJson(route('api.v1.rides.driver.show', $ride))->assertOk()->json('photo_url');

        expect($onRide)->toStartWith('/api/v1/rides/'.$ride->id.'/driver/photo?')->and($onProfile)->toBe($onRide);
    });

    it('keeps the same link within the hour, so the phone keeps the picture', function () {
        // Early in an hour, so that twenty minutes later is still the same hour whatever the time of the day is.
        $this->travelTo(now()->startOfHour()->addMinutes(5));

        $profile = DriverProfile::factory()->approved()->create();
        $ride = Ride::factory()->accepted($profile->user)->create();

        Sanctum::actingAs($ride->passenger);

        $first = $this->getJson(route('api.v1.rides.show', $ride))->json('driver.photo_url');

        $this->travel(20)->minutes();

        expect($this->getJson(route('api.v1.rides.show', $ride))->json('driver.photo_url'))->toBe($first);
    });

    it('shows the photo with the signed link and with nothing else', function () {
        Storage::fake();

        $profile = DriverProfile::factory()->approved()->create();
        Storage::put('driver-documents/'.$profile->id.'/face.jpg', 'face');
        $profile->documents()->where('type', DocumentType::Photo)->update(['path' => 'driver-documents/'.$profile->id.'/face.jpg']);
        $ride = Ride::factory()->accepted($profile->user)->create();

        Sanctum::actingAs($ride->passenger);

        $url = $this->getJson(route('api.v1.rides.show', $ride))->json('driver.photo_url');

        auth()->forgetGuards();

        $this->get($url)->assertOk();
        $this->get(preg_replace('/signature=[^&]+/', 'signature=wrong', $url))->assertForbidden();
        $this->get('/api/v1/rides/'.$ride->id.'/driver/photo')->assertForbidden();
    });

    it('has no link when the driver has no photo', function () {
        $profile = DriverProfile::factory()->create();
        $ride = Ride::factory()->accepted($profile->user)->create();

        Sanctum::actingAs($ride->passenger);

        $this->getJson(route('api.v1.rides.show', $ride))->assertOk()->assertJsonPath('driver.photo_url', null);
        $this->getJson(route('api.v1.rides.driver.show', $ride))->assertOk()->assertJsonPath('photo_url', null);
    });
});
