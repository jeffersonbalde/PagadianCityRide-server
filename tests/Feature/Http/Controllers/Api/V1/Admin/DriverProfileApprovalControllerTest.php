<?php

use App\DocumentStatus;
use App\DriverStatus;
use App\Exceptions\SmsDeliveryFailed;
use App\Mail\PlainMessageMail;
use App\Models\DriverProfile;
use App\Models\User;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Symfony\Component\Mailer\Exception\TransportException;

it('approves the application, verifies the documents and texts the driver', function () {
    $this->freezeTime();

    $admin = User::factory()->admin()->create();
    $driver = User::factory()->driver()->create(['phone' => '+639171234567']);
    $profile = DriverProfile::factory()->for($driver)->submitted()->create();

    Sanctum::actingAs($admin);

    $this->postJson(route('api.v1.admin.driver-profiles.approval.store', $profile))
        ->assertOk()
        ->assertJsonPath('status', 'approved')
        ->assertJsonPath('reviewed_at', now()->toIso8601String())
        ->assertJsonPath('documents.0.status', 'verified');

    $profile->refresh();

    expect($profile->status)->toBe(DriverStatus::Approved);
    expect($profile->reviewed_by)->toBe($admin->id);
    expect($profile->documents->pluck('status')->unique()->all())->toBe([DocumentStatus::Verified]);

    expect(sms()->sent())->toHaveCount(1);
    expect(sms()->sent()[0]['phone'])->toBe('+639171234567');
    expect(sms()->sent()[0]['message'])->toContain('approved');
});

it('lets the driver see the approval', function () {
    $driver = User::factory()->driver()->create();
    $profile = DriverProfile::factory()->for($driver)->submitted()->create();

    Sanctum::actingAs(User::factory()->admin()->create());
    $this->postJson(route('api.v1.admin.driver-profiles.approval.store', $profile))->assertOk();

    Sanctum::actingAs($driver);
    $this->getJson(route('api.v1.me.show'))->assertOk()->assertJsonPath('driver_status', 'approved');
});

it('returns 403 when the application is not waiting for review', function (string $state) {
    $profile = DriverProfile::factory()->{$state}()->create();

    Sanctum::actingAs(User::factory()->admin()->create());

    $this->postJson(route('api.v1.admin.driver-profiles.approval.store', $profile))
        ->assertForbidden()
        ->assertJsonPath('message', 'This application is not waiting for review.');

    expect(sms()->sent())->toBeEmpty();
})->with(['approved', 'rejected']);

it('returns 403 for a draft', function () {
    $profile = DriverProfile::factory()->create();

    Sanctum::actingAs(User::factory()->admin()->create());

    $this->postJson(route('api.v1.admin.driver-profiles.approval.store', $profile))->assertForbidden();

    expect($profile->fresh()->status)->toBe(DriverStatus::Draft);
});

it('keeps the approval when the text message cannot be sent', function () {
    config(['services.sms.driver' => 'semaphore', 'services.semaphore.key' => 'test-key']);
    Http::preventStrayRequests();
    Http::fake(['api.semaphore.co/api/v4/priority' => Http::response(['error' => 'down'], 500)]);
    Exceptions::fake();

    $profile = DriverProfile::factory()->submitted()->create();

    Sanctum::actingAs(User::factory()->admin()->create());

    $this->postJson(route('api.v1.admin.driver-profiles.approval.store', $profile))
        ->assertOk()
        ->assertJsonPath('status', 'approved');

    Exceptions::assertReported(SmsDeliveryFailed::class);
    expect($profile->fresh()->status)->toBe(DriverStatus::Approved);
});

it('emails the driver instead of texting when the phone number is not verified', function () {
    Mail::fake();

    $driver = User::factory()->driver()->create(['email' => 'ramil@example.com', 'phone' => '+639171234567', 'phone_verified_at' => null]);
    $profile = DriverProfile::factory()->for($driver)->submitted()->create();

    Sanctum::actingAs(User::factory()->admin()->create());

    $this->postJson(route('api.v1.admin.driver-profiles.approval.store', $profile))->assertOk();

    expect(sms()->sent())->toBeEmpty();

    Mail::assertSent(PlainMessageMail::class, fn (PlainMessageMail $mail): bool => $mail->hasTo('ramil@example.com')
        && str_contains($mail->body, 'approved'));
});

it('still approves when the email cannot be sent', function () {
    Exceptions::fake();
    Mail::shouldReceive('to->send')->andThrow(new TransportException('connection refused'));

    $driver = User::factory()->driver()->create(['email' => 'ramil@example.com', 'phone_verified_at' => null]);
    $profile = DriverProfile::factory()->for($driver)->submitted()->create();

    Sanctum::actingAs(User::factory()->admin()->create());

    $this->postJson(route('api.v1.admin.driver-profiles.approval.store', $profile))->assertOk();

    Exceptions::assertReported(TransportException::class);
    expect($profile->fresh()->status)->toBe(DriverStatus::Approved);
});
