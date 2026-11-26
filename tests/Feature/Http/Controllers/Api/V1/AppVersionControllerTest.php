<?php

use App\AppPlatform;
use App\Models\AppVersion;

function appVersionUrl(string $platform, string $version): string
{
    return route('api.v1.app-version.show', ['platform' => $platform, 'version' => $version]);
}

it('requires no update while no oldest version is set', function () {
    $this->getJson(appVersionUrl('android', '1.0.0'))
        ->assertOk()
        ->assertExactJson([
            'update_required' => false,
            'store_url' => config('mobile.store_urls.android'),
        ]);
});

it('requires an update only below the oldest version allowed', function (string $installed, bool $required) {
    AppVersion::factory()->create(['minimum_version' => '1.0.10']);

    $this->getJson(appVersionUrl('android', $installed))
        ->assertOk()
        ->assertJsonPath('update_required', $required);
})->with([
    'older' => ['1.0.9', true],
    'the same' => ['1.0.10', false],
    'newer' => ['1.1.0', false],
]);

it('keeps the platforms apart and answers with their own store', function () {
    AppVersion::factory()->create(['platform' => AppPlatform::Android, 'minimum_version' => '2.0.0']);

    $this->getJson(appVersionUrl('ios', '1.0.0'))
        ->assertOk()
        ->assertExactJson([
            'update_required' => false,
            'store_url' => config('mobile.store_urls.ios'),
        ]);
});

it('refuses a question it cannot answer', function (string $platform, string $version, string $field) {
    $this->getJson(appVersionUrl($platform, $version))
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);
})->with([
    'unknown platform' => ['web', '1.0.0', 'platform'],
    'unreadable version' => ['android', 'latest', 'version'],
]);
