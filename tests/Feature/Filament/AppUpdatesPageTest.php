<?php

use App\AppPlatform;
use App\Filament\Pages\AppUpdates;
use App\Models\AppVersion;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    Filament::setCurrentPanel('admin');
});

it('shows the versions that are set and saves new ones', function () {
    $this->actingAs(User::factory()->admin()->create());
    AppVersion::factory()->create(['platform' => AppPlatform::Android, 'minimum_version' => '1.0.1']);

    Livewire::test(AppUpdates::class)
        ->assertSchemaStateSet(['android' => '1.0.1', 'ios' => null])
        ->fillForm(['android' => '1.0.2', 'ios' => '1.0.3'])
        ->call('review')
        ->assertActionMounted('save')
        ->callMountedAction()
        ->assertNotified('Saved');

    expect(AppVersion::query()->toBase()->pluck('minimum_version', 'platform')->all())
        ->toBe(['android' => '1.0.2', 'ios' => '1.0.3']);
});

it('requires nothing again when the version is taken away', function () {
    $this->actingAs(User::factory()->admin()->create());
    AppVersion::factory()->create(['platform' => AppPlatform::Android, 'minimum_version' => '1.0.2']);

    Livewire::test(AppUpdates::class)
        ->fillForm(['android' => ''])
        ->call('review')
        ->callMountedAction()
        ->assertHasNoFormErrors();

    expect(AppVersion::requiresUpdate(AppPlatform::Android, '1.0.0'))->toBeFalse();
});

it('does not ask to save a version that is not three numbers', function () {
    $this->actingAs(User::factory()->admin()->create());

    Livewire::test(AppUpdates::class)
        ->fillForm(['android' => '1.2'])
        ->call('review')
        ->assertHasFormErrors(['android'])
        ->assertActionNotMounted();

    expect(AppVersion::query()->count())->toBe(0);
});

it('changes nothing until the admin confirms', function () {
    $this->actingAs(User::factory()->admin()->create());

    Livewire::test(AppUpdates::class)
        ->fillForm(['android' => '9.0.0'])
        ->call('review')
        ->assertActionMounted('save');

    expect(AppVersion::query()->count())->toBe(0);
});

it('is closed to people who are not admins', function () {
    $this->actingAs(User::factory()->create());

    $this->get(AppUpdates::getUrl())->assertForbidden();
});
