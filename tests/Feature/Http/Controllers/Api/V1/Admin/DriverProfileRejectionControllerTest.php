<?php

use App\DocumentStatus;
use App\DocumentType;
use App\DriverStatus;
use App\Models\DriverProfile;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('sends the application back with a reason and texts the driver', function () {
    $admin = User::factory()->admin()->create();
    $driver = User::factory()->driver()->create(['phone' => '+639171234567']);
    $profile = DriverProfile::factory()->for($driver)->submitted()->create();

    Sanctum::actingAs($admin);

    $this->postJson(route('api.v1.admin.driver-profiles.rejection.store', $profile), [
        'reason' => 'The franchise photo is blurry.',
    ])
        ->assertOk()
        ->assertJsonPath('status', 'rejected')
        ->assertJsonPath('rejection_reason', 'The franchise photo is blurry.');

    $profile->refresh();

    expect($profile->status)->toBe(DriverStatus::Rejected);
    expect($profile->reviewed_by)->toBe($admin->id);
    expect($profile->reviewed_at)->not->toBeNull();

    expect(sms()->sent())->toHaveCount(1);
    expect(sms()->sent()[0]['phone'])->toBe('+639171234567');
    expect(sms()->sent()[0]['message'])->toContain('needs changes')->not->toContain('blurry');
});

it('marks only the listed documents as rejected', function () {
    $profile = DriverProfile::factory()->submitted()->create();

    Sanctum::actingAs(User::factory()->admin()->create());

    $this->postJson(route('api.v1.admin.driver-profiles.rejection.store', $profile), [
        'reason' => 'Not readable.',
        'document_types' => ['license', 'franchise'],
    ])->assertOk();

    $statuses = $profile->documents()->get()->mapWithKeys(fn ($document) => [$document->type->value => $document->status]);

    expect($statuses['license'])->toBe(DocumentStatus::Rejected);
    expect($statuses['franchise'])->toBe(DocumentStatus::Rejected);
    expect($statuses['photo'])->toBe(DocumentStatus::Pending);
    expect($statuses['plate-papers'])->toBe(DocumentStatus::Pending);
    expect($profile->documents()->where('type', DocumentType::License)->value('rejection_reason'))->toBe('Not readable.');
});

it('lets the driver see the reason and which documents to replace', function () {
    $driver = User::factory()->driver()->create();
    $profile = DriverProfile::factory()->for($driver)->submitted()->create();

    Sanctum::actingAs(User::factory()->admin()->create());
    $this->postJson(route('api.v1.admin.driver-profiles.rejection.store', $profile), [
        'reason' => 'Not readable.',
        'document_types' => ['license'],
    ])->assertOk();

    Sanctum::actingAs($driver);
    $this->getJson(route('api.v1.driver.profile.show'))
        ->assertOk()
        ->assertJsonPath('status', 'rejected')
        ->assertJsonPath('rejection_reason', 'Not readable.')
        ->assertJsonPath('can_submit', false)
        ->assertJsonFragment(['type' => 'license', 'status' => 'rejected', 'rejection_reason' => 'Not readable.']);
});

it('returns 422 when the reason or a document type is not valid', function (array $payload, string $field) {
    $profile = DriverProfile::factory()->submitted()->create();

    Sanctum::actingAs(User::factory()->admin()->create());

    $this->postJson(route('api.v1.admin.driver-profiles.rejection.store', $profile), $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors([$field]);

    expect($profile->fresh()->status)->toBe(DriverStatus::Submitted);
})->with([
    'no reason' => [[], 'reason'],
    'reason too long' => [['reason' => str_repeat('a', 501)], 'reason'],
    'unknown document' => [['reason' => 'No.', 'document_types' => ['passport']], 'document_types.0'],
    'same document twice' => [['reason' => 'No.', 'document_types' => ['photo', 'photo']], 'document_types.0'],
]);

it('returns 403 when the application is not waiting for review', function (string $state) {
    $profile = DriverProfile::factory()->{$state}()->create();

    Sanctum::actingAs(User::factory()->admin()->create());

    $this->postJson(route('api.v1.admin.driver-profiles.rejection.store', $profile), ['reason' => 'No.'])
        ->assertForbidden()
        ->assertJsonPath('message', 'This application is not waiting for review.');

    expect(sms()->sent())->toBeEmpty();
})->with(['approved', 'rejected']);
