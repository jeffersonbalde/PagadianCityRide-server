<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\DocumentType;
use App\Http\Controllers\Controller;
use App\Models\DriverProfile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DriverProfileDocumentController extends Controller
{
    /**
     * Show the photo of one document of an application.
     */
    public function show(DriverProfile $driverProfile, DocumentType $documentType): StreamedResponse
    {
        Gate::authorize('view', $driverProfile);

        $document = $driverProfile->documents()->where('type', $documentType)->firstOrFail();

        return Storage::response($document->path, null, ['Cache-Control' => 'private, no-store']);
    }
}
