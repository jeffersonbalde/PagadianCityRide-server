<?php

namespace App\Actions\Driver;

use App\DocumentStatus;
use App\DocumentType;
use App\DriverStatus;
use App\Models\DriverProfile;
use Illuminate\Validation\ValidationException;

class SubmitDriverApplication
{
    /**
     * Send the application for review once it is complete.
     *
     * @throws ValidationException when the name or a document is missing, or a
     *                             document that was sent back is still the old one
     */
    public function handle(DriverProfile $driverProfile): DriverProfile
    {
        $driverProfile->load(['user', 'documents']);

        $errors = [];

        if (blank($driverProfile->user->name)) {
            $errors['name'] = 'Add your name before you send the application.';
        }

        $missing = $driverProfile->missingDocumentTypes();

        if ($missing !== []) {
            $errors['documents'] = 'Add all documents first. Missing: '.implode(', ', array_map(
                fn (DocumentType $type): string => $type->value,
                $missing,
            )).'.';
        } elseif ($driverProfile->documents->contains(fn ($document): bool => $document->status === DocumentStatus::Rejected)) {
            $errors['documents'] = 'Replace the documents that were sent back first.';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        $driverProfile->update([
            'status' => DriverStatus::Submitted,
            'submitted_at' => now(),
            'rejection_reason' => null,
            'reviewed_at' => null,
            'reviewed_by' => null,
        ]);

        return $driverProfile;
    }
}
