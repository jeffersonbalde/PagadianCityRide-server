<?php

use App\Filament\Resources\TripResource\Pages\ListTrips;
use App\Models\Ride;
use App\Models\RideMessage;
use App\Models\RideReport;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    Filament::setCurrentPanel('admin');
    $this->actingAs(User::factory()->admin()->create());
});

it('lists all trips and narrows to the tab that is chosen', function () {
    $waiting = Ride::factory()->create();
    $completed = Ride::factory()->completed()->create();
    $cancelled = Ride::factory()->cancelled()->create();

    Livewire::test(ListTrips::class)
        ->assertCanSeeTableRecords([$waiting, $completed, $cancelled])
        ->set('activeTab', 'completed')
        ->assertCanSeeTableRecords([$completed])
        ->assertCanNotSeeTableRecords([$waiting, $cancelled])
        ->set('activeTab', 'active')
        ->assertCanSeeTableRecords([$waiting])
        ->assertCanNotSeeTableRecords([$completed, $cancelled]);
});

it('filters by the day in the Philippines', function () {
    // 1 AM on the 10th in Pagadian is still the 9th in UTC.
    $early = Ride::factory()->create(['created_at' => '2026-11-09 17:00:00']);
    $dayBefore = Ride::factory()->create(['created_at' => '2026-11-09 15:00:00']);

    Livewire::test(ListTrips::class)
        ->filterTable('requested', ['from' => '2026-11-10', 'until' => '2026-11-10'])
        ->assertCanSeeTableRecords([$early])
        ->assertCanNotSeeTableRecords([$dayBefore]);
});

it('downloads the trips as a file', function () {
    Ride::factory()->completed()->create();

    Livewire::test(ListTrips::class)
        ->callAction('export')
        ->assertFileDownloaded('trips-'.now('Asia/Manila')->format('Y-m-d').'.csv');
});

it('shows the people, the places and the rating of a trip', function () {
    $ride = Ride::factory()->completed()->create(['rating' => 5, 'review' => 'Very kind driver.', 'rated_at' => now()]);

    $this->get(route('filament.admin.resources.trips.view', $ride))
        ->assertOk()
        ->assertSee($ride->passenger->name)
        ->assertSee($ride->driver->name)
        ->assertSee('Balangasan')
        ->assertSee('Very kind driver.');
});

it('keeps the chat private when nobody reported the trip', function () {
    $ride = Ride::factory()->completed()->create();
    RideMessage::factory()->create(['ride_id' => $ride->id, 'body' => 'I am near the blue gate.']);

    $this->get(route('filament.admin.resources.trips.view', $ride))
        ->assertOk()
        ->assertDontSee('I am near the blue gate.');
});

it('shows the chat of a reported trip', function () {
    $ride = Ride::factory()->completed()->create();
    RideMessage::factory()->create(['ride_id' => $ride->id, 'body' => 'I am near the blue gate.']);
    RideReport::factory()->create(['ride_id' => $ride->id, 'reporter_id' => $ride->passenger_id, 'reported_user_id' => $ride->driver_id]);

    $this->get(route('filament.admin.resources.trips.view', $ride))
        ->assertOk()
        ->assertSee('I am near the blue gate.');
});

it('keeps passengers away from the trips and the users', function (string $page) {
    $this->actingAs(User::factory()->create());

    $this->get($page)->assertForbidden();
})->with(['/admin/trips', '/admin/users']);
