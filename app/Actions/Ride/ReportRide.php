<?php

namespace App\Actions\Ride;

use App\Models\Ride;
use App\Models\RideReport;
use App\Models\User;
use App\ReportCategory;
use App\ReportStatus;
use Illuminate\Database\UniqueConstraintViolationException;

class ReportRide
{
    /**
     * Let one person on the ride tell the admins what went wrong with the other person. Each
     * person reports a ride once, so a ride cannot be used to flood the admins.
     */
    public function handle(Ride $ride, User $reporter, ReportCategory $category, ?string $details = null): RideReport
    {
        abort_if($ride->reports()->where('reporter_id', $reporter->id)->exists(), 409, 'You already reported this ride.');

        try {
            return $ride->reports()->create([
                'reporter_id' => $reporter->id,
                'reported_user_id' => $ride->passenger_id === $reporter->id ? $ride->driver_id : $ride->passenger_id,
                'category' => $category,
                'details' => $details,
                'status' => ReportStatus::Open,
            ]);
        } catch (UniqueConstraintViolationException) {
            // Two taps at the same moment: the first one won.
            abort(409, 'You already reported this ride.');
        }
    }
}
