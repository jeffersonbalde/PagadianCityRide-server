<?php

use App\Filament\Resources\UserResource\Pages\ListUsers;
use App\Filament\Resources\UserResource\Pages\ViewUser;
use App\Filament\Resources\UserResource\RelationManagers\DriverTripsRelationManager;
use App\Filament\Resources\UserResource\RelationManagers\PassengerTripsRelationManager;
use App\Filament\Resources\UserResource\RelationManagers\ReportsReceivedRelationManager;
use App\Filament\Widgets\UserTotals;
use App\Models\DeviceToken;
use App\Models\DriverProfile;
use App\Models\Ride;
use App\Models\RideReport;
use App\Models\User;
use App\RideStatus;
use App\Support\CsvDownload;
use App\UserRole;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    Filament::setCurrentPanel('admin');
    $this->admin = User::factory()->admin()->create();
    $this->actingAs($this->admin);
});

describe('list of users', function () {
    it('lists everybody and narrows to the tab that is chosen', function () {
        $passenger = User::factory()->create();
        $driver = User::factory()->driver()->create();
        $blocked = User::factory()->blocked()->create();

        Livewire::test(ListUsers::class)
            ->assertCanSeeTableRecords([$passenger, $driver, $blocked])
            ->set('activeTab', 'drivers')
            ->assertCanSeeTableRecords([$driver])
            ->assertCanNotSeeTableRecords([$passenger, $blocked])
            ->set('activeTab', 'blocked')
            ->assertCanSeeTableRecords([$blocked])
            ->assertCanNotSeeTableRecords([$passenger, $driver]);
    });

    it('finds a person by name, number or email', function (string $column) {
        $wanted = User::factory()->create(['name' => 'Maria Santos', 'phone' => '+639171234567', 'email' => 'maria@example.com']);
        $other = User::factory()->create();

        Livewire::test(ListUsers::class)
            ->searchTable($wanted->{$column})
            ->assertCanSeeTableRecords([$wanted])
            ->assertCanNotSeeTableRecords([$other]);
    })->with(['name', 'phone', 'email']);

    it('downloads the list as a file', function () {
        User::factory()->create();

        Livewire::test(ListUsers::class)
            ->callAction('export')
            ->assertFileDownloaded('users-'.now('Asia/Manila')->format('Y-m-d').'.csv');
    });

    it('writes a typed value that looks like a spreadsheet formula as text', function (string $typed) {
        expect(CsvDownload::safeCell($typed))->toBe("'".$typed);
    })->with(['=HYPERLINK("https://evil.test")', '+1+1', '-2+3', '@SUM(A1)']);

    it('counts the people on the dashboard', function () {
        User::factory()->count(2)->create();
        User::factory()->blocked()->driver()->create();

        Livewire::test(UserTotals::class)->assertSee('Passengers')->assertSee('Drivers')->assertSee('Blocked');
    });
});

describe('page of a user', function () {
    it('shows the account, the tricycle and the trips of a driver', function () {
        $profile = DriverProfile::factory()->approved()->create(['plate_number' => 'ABC 1234']);
        $ride = Ride::factory()->completed($profile->user)->create();

        $this->get(route('filament.admin.resources.users.view', $profile->user))
            ->assertOk()
            ->assertSee($profile->user->email)
            ->assertSee('ABC 1234');

        Livewire::test(DriverTripsRelationManager::class, ['ownerRecord' => $profile->user, 'pageClass' => ViewUser::class])
            ->assertCanSeeTableRecords([$ride]);
    });

    it('shows only the trips and reports of that person', function () {
        $ride = Ride::factory()->completed()->create();
        $other = Ride::factory()->completed()->create();
        $report = RideReport::factory()->create();

        Livewire::test(PassengerTripsRelationManager::class, ['ownerRecord' => $ride->passenger, 'pageClass' => ViewUser::class])
            ->assertCanSeeTableRecords([$ride])
            ->assertCanNotSeeTableRecords([$other]);

        Livewire::test(ReportsReceivedRelationManager::class, ['ownerRecord' => $report->reportedUser, 'pageClass' => ViewUser::class])
            ->assertCanSeeTableRecords([$report]);
    });
});

describe('edit', function () {
    it('saves the name and the number the way numbers are stored, and the new number is not verified', function () {
        $user = User::factory()->create(['phone' => '+639170000000']);

        Livewire::test(ViewUser::class, ['record' => $user->id])
            ->callAction('edit', ['name' => 'Juan Cruz', 'phone' => '0917 123 4567'])
            ->assertHasNoActionErrors()
            ->assertNotified('Saved');

        expect($user->refresh()->name)->toBe('Juan Cruz');
        expect($user->phone)->toBe('+639171234567');
        expect($user->phone_verified_at)->toBeNull();
    });

    it('keeps the number verified when only the name changes', function () {
        $user = User::factory()->create(['phone' => '+639171234567']);

        Livewire::test(ViewUser::class, ['record' => $user->id])
            ->callAction('edit', ['name' => 'Juan Cruz', 'phone' => '+639171234567'])
            ->assertHasNoActionErrors();

        expect($user->refresh()->phone_verified_at)->not->toBeNull();
    });

    it('refuses a number that is wrong or belongs to somebody else', function (string $phone) {
        User::factory()->create(['phone' => '+639179999999']);
        $user = User::factory()->create(['phone' => '+639170000000']);

        Livewire::test(ViewUser::class, ['record' => $user->id])
            ->callAction('edit', ['name' => 'Juan Cruz', 'phone' => $phone])
            ->assertHasActionErrors(['phone']);

        expect($user->refresh()->phone)->toBe('+639170000000');
    })->with(['12345', '0917 999 9999']);
});

describe('block', function () {
    it('signs a driver out everywhere, takes them offline and remembers why', function () {
        $profile = DriverProfile::factory()->onlineAt()->create();
        $driver = $profile->user;
        $driver->createToken('phone');
        DeviceToken::factory()->for($driver)->create();

        Livewire::test(ViewUser::class, ['record' => $driver->id])
            ->callAction('block', ['reason' => 'Asked for too much money.'])
            ->assertNotified('Person blocked');

        expect($driver->refresh()->isBlocked())->toBeTrue();
        expect($driver->blocked_reason)->toBe('Asked for too much money.');
        expect($driver->blocked_by)->toBe($this->admin->id);
        expect($driver->tokens()->count())->toBe(0);
        expect($driver->deviceTokens()->count())->toBe(0);
        expect($profile->refresh()->is_online)->toBeFalse();
    });

    it('cancels the trips of a blocked passenger that are going on', function () {
        $passenger = User::factory()->create();
        $waiting = Ride::factory()->create(['passenger_id' => $passenger->id]);
        $riding = Ride::factory()->inProgress()->create(['passenger_id' => $passenger->id]);
        $finished = Ride::factory()->completed()->create(['passenger_id' => $passenger->id]);

        Livewire::test(ViewUser::class, ['record' => $passenger->id])->callAction('block', ['reason' => 'Fake bookings.']);

        expect($waiting->refresh()->status)->toBe(RideStatus::Cancelled);
        expect($riding->refresh()->status)->toBe(RideStatus::Cancelled);
        expect($finished->refresh()->status)->toBe(RideStatus::Completed);
    });

    it('gives the pickup of a blocked driver back to the waiting list and closes a trip that started', function () {
        $driver = User::factory()->driver()->create();
        $pickup = Ride::factory()->accepted($driver)->create();
        $riding = Ride::factory()->inProgress($driver)->create();

        Livewire::test(ViewUser::class, ['record' => $driver->id])->callAction('block', ['reason' => 'Unsafe driving.']);

        expect($pickup->refresh()->status)->toBe(RideStatus::Requested);
        expect($pickup->driver_id)->toBeNull();
        expect($riding->refresh()->status)->toBe(RideStatus::Cancelled);
    });

    it('lets an admin unblock a person', function () {
        $user = User::factory()->blocked()->create();

        Livewire::test(ViewUser::class, ['record' => $user->id])
            ->assertActionHidden('block')
            ->callAction('unblock')
            ->assertNotified('Person unblocked');

        expect($user->refresh()->isBlocked())->toBeFalse();
        expect($user->blocked_reason)->toBeNull();
    });

    it('keeps a blocked admin out of the panel', function () {
        $this->actingAs(User::factory()->admin()->blocked()->create());

        $this->get('/admin')->assertForbidden();
    });

    it('offers no change on an admin account', function () {
        $other = User::factory()->admin()->create();

        Livewire::test(ViewUser::class, ['record' => $other->id])
            ->assertActionHidden('edit')
            ->assertActionHidden('block')
            ->assertActionHidden('delete');
    });
});

describe('delete', function () {
    it('erases the account', function () {
        $user = User::factory()->create();

        Livewire::test(ViewUser::class, ['record' => $user->id])
            ->callAction('delete')
            ->assertNotified('Account deleted');

        $this->assertModelMissing($user);
    });

    it('refuses while the person is on a trip', function () {
        $ride = Ride::factory()->inProgress()->create();

        Livewire::test(ViewUser::class, ['record' => $ride->passenger_id])
            ->callAction('delete')
            ->assertNotified('Finish your trip first.');

        expect(User::query()->where('role', UserRole::Passenger)->whereKey($ride->passenger_id)->exists())->toBeTrue();
    });
});
