<?php

use App\DocumentType;
use App\Models\DriverDocument;
use App\Models\DriverProfile;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    Storage::fake();
});

it('returns the photo of a document of any driver', function () {
    $profile = DriverProfile::factory()->submitted()->create();
    $path = UploadedFile::fake()->image('license.jpg')->store('driver-documents/'.$profile->id);
    $profile->documents()->where('type', DocumentType::License)->update(['path' => $path]);

    Sanctum::actingAs(User::factory()->admin()->create());

    $this->get(route('api.v1.admin.driver-profiles.documents.show', [$profile, 'license']))
        ->assertOk()
        ->assertHeader('Content-Type', 'image/jpeg')
        ->assertHeader('Cache-Control', 'no-store, private');
});

it('returns 404 when the document was not added', function () {
    $profile = DriverProfile::factory()->create();
    DriverDocument::factory()->for($profile)->create(['type' => DocumentType::Photo]);

    Sanctum::actingAs(User::factory()->admin()->create());

    $this->getJson(route('api.v1.admin.driver-profiles.documents.show', [$profile, 'license']))->assertNotFound();
});
