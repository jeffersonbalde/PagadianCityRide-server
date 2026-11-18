<?php

use App\DriverStatus;
use App\Models\DriverProfile;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

function validProfilePayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Ramil Mendoza',
        'tricycle_number' => '#214',
        'plate_number' => 'ABC 1234',
        'franchise_number' => '0214-2024',
    ], $overrides);
}

describe('show', function () {
    it('returns 404 when the driver has not started the application', function () {
        Sanctum::actingAs(User::factory()->driver()->create());

        $this->getJson(route('api.v1.driver.profile.show'))
            ->assertNotFound()
            ->assertJsonPath('message', 'You have not started the application yet.');
    });

    it('returns the application with its documents and what is still missing', function () {
        $driver = User::factory()->driver()->create(['name' => 'Ramil Mendoza', 'phone' => '+639171234567']);
        DriverProfile::factory()->for($driver)->create(['tricycle_number' => '#214']);

        Sanctum::actingAs($driver);

        $this->getJson(route('api.v1.driver.profile.show'))
            ->assertOk()
            ->assertJsonPath('status', 'draft')
            ->assertJsonPath('name', 'Ramil Mendoza')
            ->assertJsonPath('phone', '+639171234567')
            ->assertJsonPath('tricycle_number', '#214')
            ->assertJsonPath('documents', [])
            ->assertJsonPath('missing_documents', ['photo', 'license', 'plate-papers', 'franchise'])
            ->assertJsonPath('can_submit', false);
    });

    it('says the application can be sent when every document was added', function () {
        $driver = User::factory()->driver()->create();
        DriverProfile::factory()->for($driver)->withAllDocuments()->create();

        Sanctum::actingAs($driver);

        $this->getJson(route('api.v1.driver.profile.show'))
            ->assertOk()
            ->assertJsonCount(4, 'documents')
            ->assertJsonPath('missing_documents', [])
            ->assertJsonPath('can_submit', true);
    });

    it('tells the app whether the driver is online, so it can pick up where it left off', function () {
        $offline = User::factory()->driver()->create();
        DriverProfile::factory()->for($offline)->approved()->create();

        Sanctum::actingAs($offline);
        $this->getJson(route('api.v1.driver.profile.show'))->assertOk()->assertJsonPath('is_online', false);

        Sanctum::actingAs(onlineDriver());
        $this->getJson(route('api.v1.driver.profile.show'))->assertOk()->assertJsonPath('is_online', true);
    });

    it('does not show the application of another driver', function () {
        DriverProfile::factory()->create();

        Sanctum::actingAs(User::factory()->driver()->create());

        $this->getJson(route('api.v1.driver.profile.show'))->assertNotFound();
    });
});

describe('update', function () {
    it('starts the application and saves the name on the account', function () {
        $driver = User::factory()->driver()->phoneOnly()->create();

        Sanctum::actingAs($driver);

        $this->putJson(route('api.v1.driver.profile.update'), validProfilePayload())
            ->assertCreated()
            ->assertJsonPath('status', 'draft')
            ->assertJsonPath('name', 'Ramil Mendoza')
            ->assertJsonPath('plate_number', 'ABC 1234');

        expect($driver->fresh()->name)->toBe('Ramil Mendoza');
        expect($driver->fresh()->driverProfile->only(['tricycle_number', 'plate_number', 'franchise_number']))->toBe([
            'tricycle_number' => '#214',
            'plate_number' => 'ABC 1234',
            'franchise_number' => '0214-2024',
        ]);
        expect($driver->fresh()->driverProfile->status)->toBe(DriverStatus::Draft);
    });

    it('writes the plate and franchise numbers in capital letters', function () {
        Sanctum::actingAs(User::factory()->driver()->create());

        $this->putJson(route('api.v1.driver.profile.update'), validProfilePayload([
            'plate_number' => '  abc   1234 ',
            'franchise_number' => 'mtop-0214',
        ]))
            ->assertCreated()
            ->assertJsonPath('plate_number', 'ABC 1234')
            ->assertJsonPath('franchise_number', 'MTOP-0214');
    });

    it('changes the details of an application that is still a draft', function () {
        $driver = User::factory()->driver()->create();
        DriverProfile::factory()->for($driver)->create(['plate_number' => 'ABC 1234']);

        Sanctum::actingAs($driver);

        $this->putJson(route('api.v1.driver.profile.update'), validProfilePayload(['plate_number' => 'XYZ 9999']))
            ->assertOk()
            ->assertJsonPath('plate_number', 'XYZ 9999');

        $this->assertDatabaseCount('driver_profiles', 1);
    });

    it('keeps an application that was sent back as sent back while the driver edits it', function () {
        $driver = User::factory()->driver()->create();
        DriverProfile::factory()->for($driver)->rejected('Photos are blurry.')->create();

        Sanctum::actingAs($driver);

        $this->putJson(route('api.v1.driver.profile.update'), validProfilePayload())
            ->assertOk()
            ->assertJsonPath('status', 'rejected')
            ->assertJsonPath('rejection_reason', 'Photos are blurry.');
    });

    it('lets the driver save the same plate and franchise numbers again', function () {
        $driver = User::factory()->driver()->create();
        DriverProfile::factory()->for($driver)->create([
            'plate_number' => 'ABC 1234',
            'franchise_number' => '0214-2024',
        ]);

        Sanctum::actingAs($driver);

        $this->putJson(route('api.v1.driver.profile.update'), validProfilePayload())->assertOk();
    });

    it('returns 403 when the application was already sent or approved', function (DriverStatus $status) {
        $driver = User::factory()->driver()->create();
        $profile = DriverProfile::factory()->for($driver)->create(['status' => $status, 'plate_number' => 'ABC 1234']);

        Sanctum::actingAs($driver);

        $this->putJson(route('api.v1.driver.profile.update'), validProfilePayload(['plate_number' => 'XYZ 9999']))
            ->assertForbidden()
            ->assertJsonPath('message', 'The application can no longer be changed.');

        expect($profile->fresh()->plate_number)->toBe('ABC 1234');
    })->with([
        'submitted' => DriverStatus::Submitted,
        'approved' => DriverStatus::Approved,
    ]);

    it('returns 422 when the details are missing', function () {
        Sanctum::actingAs(User::factory()->driver()->create());

        $this->putJson(route('api.v1.driver.profile.update'), [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'tricycle_number', 'plate_number', 'franchise_number']);

        $this->assertDatabaseCount('driver_profiles', 0);
    });

    it('returns 422 when the plate number belongs to another driver', function () {
        DriverProfile::factory()->create(['plate_number' => 'ABC 1234']);

        Sanctum::actingAs(User::factory()->driver()->create());

        $this->putJson(route('api.v1.driver.profile.update'), validProfilePayload(['plate_number' => 'abc 1234']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['plate_number' => 'This plate number is already registered.']);
    });

    it('returns 422 when the franchise number belongs to another driver', function () {
        DriverProfile::factory()->create(['franchise_number' => '0214-2024']);

        Sanctum::actingAs(User::factory()->driver()->create());

        $this->putJson(route('api.v1.driver.profile.update'), validProfilePayload())
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['franchise_number' => 'This franchise number is already registered.']);
    });

    it('returns 422 when a value is too long', function (string $field, int $length) {
        Sanctum::actingAs(User::factory()->driver()->create());

        $this->putJson(route('api.v1.driver.profile.update'), validProfilePayload([$field => str_repeat('A', $length + 1)]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors([$field]);
    })->with([
        'name' => ['name', 100],
        'tricycle number' => ['tricycle_number', 20],
        'plate number' => ['plate_number', 20],
        'franchise number' => ['franchise_number', 30],
    ]);
});
