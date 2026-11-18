<?php

namespace App\Models;

use App\ReportCategory;
use App\ReportStatus;
use Database\Factories\RideReportFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A passenger or a driver telling the admins that something went wrong on a ride.
 * The reported person is always the other person on the ride.
 */
class RideReport extends Model
{
    /** @use HasFactory<RideReportFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'ride_id',
        'reporter_id',
        'reported_user_id',
        'category',
        'details',
        'status',
        'admin_note',
        'handled_by',
        'handled_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category' => ReportCategory::class,
            'status' => ReportStatus::class,
            'handled_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Ride, $this>
     */
    public function ride(): BelongsTo
    {
        return $this->belongsTo(Ride::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reportedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_user_id');
    }
}
