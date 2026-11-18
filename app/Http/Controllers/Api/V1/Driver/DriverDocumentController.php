<?php

namespace App\Http\Controllers\Api\V1\Driver;

use App\Actions\Driver\StoreDriverDocument;
use App\DocumentType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Driver\StoreDriverDocumentRequest;
use App\Http\Resources\Api\V1\DriverDocumentResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DriverDocumentController extends Controller
{
    /**
     * Show the photo of one of the documents of the signed-in driver.
     */
    public function show(Request $request, DocumentType $documentType): StreamedResponse
    {
        $document = $request->user()->driverProfile?->documents()->where('type', $documentType)->first();

        abort_if($document === null, 404, 'This document was not added yet.');

        return Storage::response($document->path, null, ['Cache-Control' => 'private, no-store']);
    }

    /**
     * Add the photo of a document, or replace the one that was added before.
     *
     * It is a POST because PHP only reads uploaded files from POST requests.
     */
    public function store(
        StoreDriverDocumentRequest $request,
        DocumentType $documentType,
        StoreDriverDocument $storeDriverDocument,
    ): JsonResponse {
        $driverProfile = $request->user()->driverProfile;

        abort_if($driverProfile === null, 409, 'Save your details before you add the documents.');

        Gate::authorize('update', $driverProfile);

        $document = $storeDriverDocument->handle($driverProfile, $documentType, $request->file('file'));

        return DriverDocumentResource::make($document)
            ->response()
            ->setStatusCode($document->wasRecentlyCreated ? 201 : 200);
    }
}
