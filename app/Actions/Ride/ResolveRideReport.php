<?php

namespace App\Actions\Ride;

use App\AlertType;
use App\Models\RideReport;
use App\Models\User;
use App\Notifications\Alert;
use App\ReportStatus;
use Illuminate\Support\Facades\DB;

class ResolveRideReport
{
    /**
     * An admin closes an open report, with a note, and the reporter is told that it was looked at.
     * The note stays with the admins: the reporter only hears that the report was reviewed.
     */
    public function handle(RideReport $report, User $admin, ReportStatus $status, string $note): RideReport
    {
        $report = DB::transaction(function () use ($report, $admin, $status, $note): RideReport {
            $current = RideReport::lockForUpdate()->findOrFail($report->id);

            abort_unless($current->status->isOpen(), 409, 'This report was already handled.');

            $current->update([
                'status' => $status,
                'admin_note' => $note,
                'handled_by' => $admin->id,
                'handled_at' => now(),
            ]);

            return $current;
        });

        $report->reporter->notify(new Alert(AlertType::ReportHandled, $report->ride));

        return $report;
    }
}
