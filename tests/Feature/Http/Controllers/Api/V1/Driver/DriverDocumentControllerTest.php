<?php

use App\DocumentStatus;
use App\DocumentType;
use App\DriverStatus;
use App\Models\DriverDocument;
use App\Models\DriverProfile;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    Storage::fake();
});

describe('store', function () {
    it('adds the photo of a document and waits for review', function () {
        $driver = User::factory()->driver()->create();
        $profile = DriverProfile::factory()->for($driver)->create();

        Sanctum::actingAs($driver);

        $this->postJson(route('api.v1.driver.documents.store', 'license'), [
            'file' => UploadedFile::fake()->image('license.jpg'),
        ])
            ->assertCreated()
            ->assertJsonPath('type', 'license')
            ->assertJsonPath('status', 'pending')
            ->assertJsonPath('rejection_reason', null)
            ->assertJsonMissingPath('path');

        $document = $profile->documents()->sole();

        expect($document->type)->toBe(DocumentType::License);
        Storage::assertExists($document->path);
    });

    it('accepts a document of every type', function (DocumentType $type) {
        $driver = User::factory()->driver()->create();
        DriverProfile::factory()->for($driver)->create();

        Sanctum::actingAs($driver);

        $this->postJson(route('api.v1.driver.documents.store', $type->value), [
            'file' => UploadedFile::fake()->image('photo.png'),
        ])->assertCreated()->assertJsonPath('type', $type->value);
    })->with(DocumentType::cases());

    it('replaces the earlier photo, deletes its file and waits for review again', function () {
        $driver = User::factory()->driver()->create();
        $profile = DriverProfile::factory()->for($driver)->create(['status' => DriverStatus::Rejected]);
        $earlierPath = UploadedFile::fake()->image('old.jpg')->store('driver-documents/'.$profile->id);
        $earlier = DriverDocument::factory()->for($profile)->rejected('Not readable.')->create([
            'type' => DocumentType::Franchise,
            'path' => $earlierPath,
        ]);

        Sanctum::actingAs($driver);

        $this->postJson(route('api.v1.driver.documents.store', 'franchise'), [
            'file' => UploadedFile::fake()->image('new.jpg'),
        ])
            ->assertOk()
            ->assertJsonPath('status', 'pending')
            ->assertJsonPath('rejection_reason', null);

        $document = $profile->documents()->sole();

        expect($document->id)->toBe($earlier->id);
        expect($document->path)->not->toBe($earlierPath);
        Storage::assertMissing($earlierPath);
        Storage::assertExists($document->path);
    });

    it('returns 409 when the driver has not saved the details yet', function () {
        Sanctum::actingAs(User::factory()->driver()->create());

        $this->postJson(route('api.v1.driver.documents.store', 'photo'), [
            'file' => UploadedFile::fake()->image('photo.jpg'),
        ])
            ->assertConflict()
            ->assertJsonPath('message', 'Save your details before you add the documents.');
    });

    it('returns 403 when the application was already sent', function () {
        $driver = User::factory()->driver()->create();
        DriverProfile::factory()->for($driver)->submitted()->create();

        Sanctum::actingAs($driver);

        $this->postJson(route('api.v1.driver.documents.store', 'photo'), [
            'file' => UploadedFile::fake()->image('photo.jpg'),
        ])->assertForbidden();

        expect(Storage::allFiles())->toBeEmpty();
    });

    it('returns 404 for a document type that does not exist', function () {
        $driver = User::factory()->driver()->create();
        DriverProfile::factory()->for($driver)->create();

        Sanctum::actingAs($driver);

        $this->postJson(route('api.v1.driver.documents.store', 'passport'), [
            'file' => UploadedFile::fake()->image('photo.jpg'),
        ])->assertNotFound();
    });

    it('returns 422 when the file is not a good photo', function (?UploadedFile $file, string $message) {
        $driver = User::factory()->driver()->create();
        DriverProfile::factory()->for($driver)->create();

        Sanctum::actingAs($driver);

        $this->postJson(route('api.v1.driver.documents.store', 'photo'), array_filter(['file' => $file]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['file' => $message]);

        $this->assertDatabaseCount('driver_documents', 0);
    })->with([
        'no file' => [null, 'Take or choose a photo of the document.'],
        'a PDF' => [fn () => UploadedFile::fake()->create('license.pdf', 100, 'application/pdf'), 'The document must be a photo.'],
        'a text file' => [fn () => UploadedFile::fake()->create('notes.txt', 1, 'text/plain'), 'The document must be a photo.'],
        'bigger than 5 MB' => [fn () => UploadedFile::fake()->image('big.jpg')->size(5121), 'The photo is too big. It can be up to 5 MB.'],
    ]);
});

describe('show', function () {
    it('returns the photo of a document of the driver', function () {
        $driver = User::factory()->driver()->create();
        $profile = DriverProfile::factory()->for($driver)->create();
        $path = UploadedFile::fake()->image('license.jpg')->store('driver-documents/'.$profile->id);
        DriverDocument::factory()->for($profile)->create(['type' => DocumentType::License, 'path' => $path]);

        Sanctum::actingAs($driver);

        $this->get(route('api.v1.driver.documents.show', 'license'))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/jpeg')
            ->assertHeader('Cache-Control', 'no-store, private');
    });

    it('returns 404 when the document was not added', function () {
        $driver = User::factory()->driver()->create();
        DriverProfile::factory()->for($driver)->create();

        Sanctum::actingAs($driver);

        $this->getJson(route('api.v1.driver.documents.show', 'license'))
            ->assertNotFound()
            ->assertJsonPath('message', 'This document was not added yet.');
    });

    it('does not return the document of another driver', function () {
        $other = DriverProfile::factory()->create();
        $path = UploadedFile::fake()->image('license.jpg')->store('driver-documents/'.$other->id);
        DriverDocument::factory()->for($other)->create(['type' => DocumentType::License, 'path' => $path]);

        Sanctum::actingAs(User::factory()->driver()->create());

        $this->getJson(route('api.v1.driver.documents.show', 'license'))->assertNotFound();
    });
});

it('keeps the status of documents that were not replaced', function () {
    $driver = User::factory()->driver()->create();
    $profile = DriverProfile::factory()->for($driver)->withAllDocuments(DocumentStatus::Verified)->create(['status' => DriverStatus::Rejected]);

    Sanctum::actingAs($driver);

    $this->postJson(route('api.v1.driver.documents.store', 'photo'), [
        'file' => UploadedFile::fake()->image('photo.jpg'),
    ])->assertOk();

    expect($profile->documents()->where('status', DocumentStatus::Verified)->count())->toBe(3);
});
