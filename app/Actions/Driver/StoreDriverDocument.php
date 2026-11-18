<?php

namespace App\Actions\Driver;

use App\DocumentStatus;
use App\DocumentType;
use App\Models\DriverDocument;
use App\Models\DriverProfile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class StoreDriverDocument
{
    /**
     * Keep the uploaded photo as the document of this type.
     *
     * A document that was uploaded before is replaced, its old file is deleted,
     * and the new one waits for review again.
     */
    public function handle(DriverProfile $driverProfile, DocumentType $type, UploadedFile $file): DriverDocument
    {
        $earlierPath = $driverProfile->documents()->where('type', $type)->value('path');

        $document = $driverProfile->documents()->updateOrCreate(
            ['type' => $type],
            [
                'path' => $file->store('driver-documents/'.$driverProfile->id),
                'status' => DocumentStatus::Pending,
                'rejection_reason' => null,
            ],
        );

        if ($earlierPath !== null) {
            Storage::delete($earlierPath);
        }

        return $document;
    }
}
