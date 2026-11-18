<?php

use App\DocumentStatus;
use App\DocumentType;
use App\DriverStatus;
use App\Models\DriverDocument;
use App\Models\DriverProfile;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('sends a complete application for review', function () {
    $this->freezeTime();

    $driver = User::factory()->driver()->create();
    $profile = DriverProfile::factory()->for($driver)->withAllDocuments()->create();

    Sanctum::actingAs($driver);

    $this->postJson(route('api.v1.driver.submission.store'))
        ->assertOk()
        ->assertJsonPath('status', 'submitted')
        ->assertJsonPath('submitted_at', now()->toIso8601String())
        ->assertJsonPath('can_submit', false);

    expect($profile->fresh()->status)->toBe(DriverStatus::Submitted);
    expect($profile->fresh()->submitted_at->toIso8601String())->toBe(now()->toIso8601String());
});

it('sends an application again after it was sent back and the documents were replaced', function () {
    $driver = User::factory()->driver()->create();
    $profile = DriverProfile::factory()->for($driver)->rejected('Photos are blurry.')->create();

    Sanctum::actingAs($driver);

    $this->postJson(route('api.v1.driver.submission.store'))
        ->assertOk()
        ->assertJsonPath('status', 'submitted')
        ->assertJsonPath('rejection_reason', null)
        ->assertJsonPath('reviewed_at', null);

    expect($profile->fresh()->reviewed_by)->toBeNull();
});

it('returns 422 and names the documents that are missing', function () {
    $driver = User::factory()->driver()->create();
    $profile = DriverProfile::factory()->for($driver)->create();
    DriverDocument::factory()->for($profile)->create(['type' => DocumentType::Photo]);
    DriverDocument::factory()->for($profile)->create(['type' => DocumentType::License]);

    Sanctum::actingAs($driver);

    $this->postJson(route('api.v1.driver.submission.store'))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['documents' => 'Add all documents first. Missing: plate-papers, franchise.']);

    expect($profile->fresh()->status)->toBe(DriverStatus::Draft);
});

it('returns 422 when a document that was sent back was not replaced', function () {
    $driver = User::factory()->driver()->create();
    $profile = DriverProfile::factory()->for($driver)->withAllDocuments()->create(['status' => DriverStatus::Rejected]);
    $profile->documents()->where('type', DocumentType::License)->update(['status' => DocumentStatus::Rejected]);

    Sanctum::actingAs($driver);

    $this->postJson(route('api.v1.driver.submission.store'))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['documents' => 'Replace the documents that were sent back first.']);

    expect($profile->fresh()->status)->toBe(DriverStatus::Rejected);
});

it('returns 422 when the driver has no name yet', function () {
    $driver = User::factory()->driver()->phoneOnly()->create();
    DriverProfile::factory()->for($driver)->withAllDocuments()->create();

    Sanctum::actingAs($driver);

    $this->postJson(route('api.v1.driver.submission.store'))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name' => 'Add your name before you send the application.']);
});

it('returns 404 when the driver has not started the application', function () {
    Sanctum::actingAs(User::factory()->driver()->create());

    $this->postJson(route('api.v1.driver.submission.store'))->assertNotFound();
});

it('returns 403 when the application was already sent or approved', function (string $state) {
    $driver = User::factory()->driver()->create();
    $profile = DriverProfile::factory()->for($driver)->{$state}()->create();

    Sanctum::actingAs($driver);

    $this->postJson(route('api.v1.driver.submission.store'))->assertForbidden();

    expect($profile->fresh()->status)->not->toBe(DriverStatus::Draft);
})->with(['submitted', 'approved']);
