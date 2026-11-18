<?php

use App\DocumentStatus;
use App\DocumentType;
use App\DriverStatus;
use App\Filament\Resources\DriverApprovalResource\Pages\ListDriverApprovals;
use App\Filament\Resources\DriverApprovalResource\Pages\ViewDriverApproval;
use App\Filament\Resources\DriverResource\Pages\ListDrivers;
use App\Filament\Resources\RideReportResource\Pages\ListRideReports;
use App\Filament\Resources\RideReportResource\Pages\ViewRideReport;
use App\Filament\Widgets\OverviewStats;
use App\Filament\Widgets\RecentTrips;
use App\Models\DriverDocument;
use App\Models\DriverProfile;
use App\Models\Ride;
use App\Models\RideReport;
use App\Models\User;
use App\ReportStatus;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Filament::setCurrentPanel('admin');
});

describe('access', function () {
    it('lets an admin open the panel', function () {
        $this->actingAs(User::factory()->admin()->create());

        $this->get('/admin')->assertOk();
    });

    it('sends a visitor to the sign-in page', function () {
        $this->get('/admin')->assertRedirect('/admin/login');
    });

    it('keeps passengers and drivers out of the panel', function () {
        $this->actingAs(User::factory()->create());
        $this->get('/admin')->assertForbidden();

        $this->actingAs(User::factory()->driver()->create());
        $this->get('/admin')->assertForbidden();
    });

});

describe('driver approvals', function () {
    beforeEach(function () {
        $this->actingAs(User::factory()->admin()->create());
    });

    it('lists the applications that wait for review and the ones that were sent back, not the approved drivers', function () {
        $waiting = DriverProfile::factory()->submitted()->create();
        $sentBack = DriverProfile::factory()->rejected()->create();
        $approved = DriverProfile::factory()->approved()->create();

        Livewire::test(ListDriverApprovals::class)
            ->assertCanSeeTableRecords([$waiting])
            ->assertCanNotSeeTableRecords([$sentBack, $approved])
            ->set('activeTab', 'sent-back')
            ->assertCanSeeTableRecords([$sentBack])
            ->assertCanNotSeeTableRecords([$waiting, $approved]);
    });

    it('approves an application, verifies the documents and texts the driver', function () {
        $profile = DriverProfile::factory()->submitted()->create();

        Livewire::test(ViewDriverApproval::class, ['record' => $profile->id])
            ->callAction('approve')
            ->assertNotified('Driver approved');

        expect($profile->refresh()->status)->toBe(DriverStatus::Approved);
        expect($profile->documents->pluck('status')->unique()->all())->toBe([DocumentStatus::Verified]);
        expect(sms()->sent())->toHaveCount(1);
    });

    it('sends an application back with a reason and marks the photos to take again', function () {
        $profile = DriverProfile::factory()->submitted()->create();

        Livewire::test(ViewDriverApproval::class, ['record' => $profile->id])
            ->callAction('sendBack', ['reason' => 'The license is blurry.', 'document_types' => [DocumentType::License->value]])
            ->assertNotified('Application sent back');

        $profile->refresh();

        expect($profile->status)->toBe(DriverStatus::Rejected);
        expect($profile->rejection_reason)->toBe('The license is blurry.');
        expect($profile->documents->firstWhere('type', DocumentType::License)->status)->toBe(DocumentStatus::Rejected);
        expect($profile->documents->firstWhere('type', DocumentType::Photo)->status)->toBe(DocumentStatus::Pending);
    });

    it('needs a reason to send an application back', function () {
        $profile = DriverProfile::factory()->submitted()->create();

        Livewire::test(ViewDriverApproval::class, ['record' => $profile->id])
            ->callAction('sendBack', ['reason' => ''])
            ->assertHasActionErrors(['reason' => 'required']);

        expect($profile->refresh()->status)->toBe(DriverStatus::Submitted);
    });

    it('has no approve button for an application that was sent back', function () {
        $profile = DriverProfile::factory()->rejected()->create();

        Livewire::test(ViewDriverApproval::class, ['record' => $profile->id])
            ->assertActionHidden('approve')
            ->assertActionHidden('sendBack');
    });

    it('shows the photo of a document only to admins', function () {
        Storage::fake();
        Storage::put('driver-documents/1/photo.jpg', 'image');

        $document = DriverDocument::factory()->create(['path' => 'driver-documents/1/photo.jpg']);

        $this->get(route('admin.driver-documents.show', $document))->assertOk();

        auth()->logout();
        $this->actingAs(User::factory()->driver()->create());

        $this->get(route('admin.driver-documents.show', $document))->assertForbidden();
    });
});

describe('drivers', function () {
    it('lists the approved drivers with their rating and finished trips, and who is online', function () {
        $this->actingAs(User::factory()->admin()->create());

        $online = DriverProfile::factory()->onlineAt()->create();
        $offline = DriverProfile::factory()->approved()->create();
        $waiting = DriverProfile::factory()->submitted()->create();

        Ride::factory()->completed($online->user)->rated(5)->create();
        Ride::factory()->completed($online->user)->rated(4)->create();

        Livewire::test(ListDrivers::class)
            ->assertCanSeeTableRecords([$online, $offline])
            ->assertCanNotSeeTableRecords([$waiting])
            ->assertTableColumnStateSet('completed_trips_count', 2, $online)
            ->assertTableColumnStateSet('online', true, $online)
            ->assertTableColumnStateSet('online', false, $offline)
            ->filterTable('online', true)
            ->assertCanSeeTableRecords([$online])
            ->assertCanNotSeeTableRecords([$offline]);
    });
});

describe('reports', function () {
    beforeEach(function () {
        $this->actingAs(User::factory()->admin()->create());
    });

    it('lists the open reports first, and the closed ones in their own tabs', function () {
        $open = RideReport::factory()->create();
        $resolved = RideReport::factory()->resolved()->create();

        Livewire::test(ListRideReports::class)
            ->assertCanSeeTableRecords([$open])
            ->assertCanNotSeeTableRecords([$resolved])
            ->set('activeTab', 'resolved')
            ->assertCanSeeTableRecords([$resolved])
            ->assertCanNotSeeTableRecords([$open]);
    });

    it('resolves a report with a note', function () {
        $report = RideReport::factory()->create();

        Livewire::test(ViewRideReport::class, ['record' => $report->id])
            ->callAction('resolve', ['note' => 'Talked to the driver.'])
            ->assertNotified('Resolved');

        $report->refresh();

        expect($report->status)->toBe(ReportStatus::Resolved);
        expect($report->admin_note)->toBe('Talked to the driver.');
        expect($report->handled_by)->not->toBeNull();
    });

    it('dismisses a report with a note', function () {
        $report = RideReport::factory()->create();

        Livewire::test(ViewRideReport::class, ['record' => $report->id])
            ->callAction('dismiss', ['note' => 'Nothing happened.'])
            ->assertNotified('Dismissed');

        expect($report->refresh()->status)->toBe(ReportStatus::Dismissed);
    });

    it('needs a note to close a report', function () {
        $report = RideReport::factory()->create();

        Livewire::test(ViewRideReport::class, ['record' => $report->id])
            ->callAction('resolve', ['note' => ''])
            ->assertHasActionErrors(['note' => 'required']);

        expect($report->refresh()->status)->toBe(ReportStatus::Open);
    });

    it('has no buttons for a report that was already closed', function () {
        $report = RideReport::factory()->resolved()->create();

        Livewire::test(ViewRideReport::class, ['record' => $report->id])
            ->assertActionHidden('resolve')
            ->assertActionHidden('dismiss');
    });
});

describe('dashboard', function () {
    it('shows the numbers of the day and the recent trips', function () {
        $this->actingAs(User::factory()->admin()->create());

        $ride = Ride::factory()->create();

        Livewire::test(RecentTrips::class)->assertCanSeeTableRecords([$ride]);

        Livewire::test(OverviewStats::class)->assertSee(['Drivers online', 'Passengers waiting', 'Trips today', 'Average wait']);
    });
});
