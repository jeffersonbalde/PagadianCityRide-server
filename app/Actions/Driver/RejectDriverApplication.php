<?php

namespace App\Actions\Driver;

use App\AlertType;
use App\DocumentStatus;
use App\DocumentType;
use App\DriverStatus;
use App\Models\DriverProfile;
use App\Models\User;
use App\Notifications\Alert;
use App\Services\UserMessenger;
use Illuminate\Support\Facades\DB;

class RejectDriverApplication
{
    public function __construct(private UserMessenger $messenger) {}

    /**
     * Send the application back to the driver with a reason. The documents in
     * the list are marked as rejected, so the driver only replaces those. The
     * driver is told by text message or by email, and a message that cannot be
     * sent does not undo the rejection.
     *
     * @param  list<DocumentType>  $documentTypes
     */
    public function handle(DriverProfile $driverProfile, User $admin, string $reason, array $documentTypes = []): DriverProfile
    {
        $driverProfile = DB::transaction(function () use ($driverProfile, $admin, $reason, $documentTypes): DriverProfile {
            $current = DriverProfile::lockForUpdate()->findOrFail($driverProfile->id);

            abort_unless($current->status === DriverStatus::Submitted, 409, 'This application is not waiting for review.');

            if ($documentTypes !== []) {
                $current->documents()->whereIn('type', $documentTypes)->update([
                    'status' => DocumentStatus::Rejected,
                    'rejection_reason' => $reason,
                ]);
            }

            $current->update([
                'status' => DriverStatus::Rejected,
                'rejection_reason' => $reason,
                'reviewed_at' => now(),
                'reviewed_by' => $admin->id,
            ]);

            return $current;
        });

        $this->messenger->send($driverProfile->user, 'Your driver application needs changes', 'Your driver application needs changes. Open the app to see what to fix.');

        $driverProfile->user->notify(new Alert(AlertType::DriverRejected));

        return $driverProfile;
    }
}
