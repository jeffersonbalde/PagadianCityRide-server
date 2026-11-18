<?php

namespace App\Actions\Auth;

use App\Models\RideMessage;
use App\Models\User;
use App\UserRole;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class DeleteAccount
{
    /**
     * Erase a person and what belongs to them: the name and phone number, the emergency contacts,
     * the phones that get notifications, the alerts, the driver application with its photos, the
     * trips they asked for and the reports they made. A trip they drove stays for the passenger,
     * without a driver.
     *
     * A person on a trip must finish it first, and an admin account is not deleted here.
     */
    public function handle(User $user): void
    {
        abort_if($user->role === UserRole::Admin, 403, 'An admin account cannot be deleted here.');
        abort_if(
            $user->ridesAsPassenger()->active()->exists() || $user->ridesAsDriver()->active()->exists(),
            409,
            'Finish your trip first.',
        );

        $driverProfileId = $user->driverProfile?->id;

        // The photos of the chats the person wrote, and of the trips that are deleted with them.
        $photoPaths = RideMessage::query()
            ->whereNotNull('image_path')
            ->where(fn ($query) => $query
                ->where('sender_id', $user->id)
                ->orWhereIn('ride_id', $user->ridesAsPassenger()->select('id')))
            ->pluck('image_path');

        DB::transaction(function () use ($user): void {
            $user->tokens()->delete();
            $user->notifications()->delete();
            $user->delete();
        });

        Storage::delete($photoPaths->all());

        if ($driverProfileId !== null) {
            Storage::deleteDirectory('driver-documents/'.$driverProfileId);
        }
    }
}
