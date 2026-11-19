<?php

namespace App\Actions\Driver;

use App\AlertType;
use App\DocumentStatus;
use App\DriverStatus;
use App\Exceptions\SmsDeliveryFailed;
use App\Models\DriverProfile;
use App\Models\User;
use App\Notifications\Alert;
use App\Services\Sms\SmsGateway;
use Illuminate\Support\Facades\DB;

class ApproveDriverApplication
{
    public function __construct(private SmsGateway $sms) {}

    /**
     * Approve the application: every document counts as verified, and the
     * driver is told by text message. A text that cannot be sent does not undo
     * the approval.
     */
    public function handle(DriverProfile $driverProfile, User $admin): DriverProfile
    {
        $driverProfile = DB::transaction(function () use ($driverProfile, $admin): DriverProfile {
            $current = DriverProfile::lockForUpdate()->findOrFail($driverProfile->id);

            abort_unless($current->status === DriverStatus::Submitted, 409, 'This application is not waiting for review.');

            $current->documents()->update([
                'status' => DocumentStatus::Verified,
                'rejection_reason' => null,
            ]);

            $current->update([
                'status' => DriverStatus::Approved,
                'rejection_reason' => null,
                'reviewed_at' => now(),
                'reviewed_by' => $admin->id,
            ]);

            return $current;
        });

        $this->tell($driverProfile, 'Your driver application is approved. You can now go online.');

        $driverProfile->user->notify(new Alert(AlertType::DriverApproved));

        return $driverProfile;
    }

    private function tell(DriverProfile $driverProfile, string $message): void
    {
        try {
            $this->sms->send($driverProfile->user->phone, config('services.sms.prefix').': '.$message);
        } catch (SmsDeliveryFailed $exception) {
            report($exception);
        }
    }
}
