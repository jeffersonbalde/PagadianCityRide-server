<?php

namespace App\Models;

use App\DocumentType;
use App\RideStatus;
use App\TripRange;
use Database\Factories\RideFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\URL;

class Ride extends Model
{
    /** @use HasFactory<RideFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'driver_id',
        'status',
        'pickup_latitude',
        'pickup_longitude',
        'pickup_label',
        'destination_latitude',
        'destination_longitude',
        'destination_label',
        'passengers_count',
        'note',
        'distance_meters',
        'duration_seconds',
        'accepted_at',
        'arrived_at',
        'started_at',
        'completed_at',
        'cancelled_at',
        'cancel_reason',
        'rating',
        'review',
        'rated_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => RideStatus::class,
            'pickup_latitude' => 'float',
            'pickup_longitude' => 'float',
            'destination_latitude' => 'float',
            'destination_longitude' => 'float',
            'passengers_count' => 'integer',
            'distance_meters' => 'integer',
            'duration_seconds' => 'integer',
            'rating' => 'integer',
            'accepted_at' => 'datetime',
            'arrived_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'rated_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function passenger(): BelongsTo
    {
        return $this->belongsTo(User::class, 'passenger_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function driver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    /**
     * @return HasMany<RideDriverCancellation, $this>
     */
    public function driverCancellations(): HasMany
    {
        return $this->hasMany(RideDriverCancellation::class);
    }

    /**
     * The reports that people made about this ride.
     *
     * @return HasMany<RideReport, $this>
     */
    public function reports(): HasMany
    {
        return $this->hasMany(RideReport::class);
    }

    /**
     * The chat between the passenger and the driver.
     *
     * @return HasMany<RideMessage, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(RideMessage::class);
    }

    /**
     * Rides that are still going on.
     *
     * @param  Builder<Ride>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->whereIn('status', RideStatus::active());
    }

    /**
     * Rides requested within the range: today, this week or all of them.
     *
     * @param  Builder<Ride>  $query
     */
    public function scopeInRange(Builder $query, TripRange $range): void
    {
        $startsAt = $range->startsAt();

        if ($startsAt !== null) {
            $query->where('created_at', '>=', $startsAt);
        }
    }

    /**
     * The relations a ride needs to be shown: the people of the ride, and the
     * rating and trip count of the driver that a passenger sees. Use it with
     * `with()` for a list and through `loadForDisplay()` for one ride.
     *
     * @return array<string, mixed>
     */
    public static function displayRelations(): array
    {
        return [
            'passenger',
            // Only the report of the person who looks at the ride, so nobody learns about the report of the other person.
            'reports' => fn ($query) => $query->where('reporter_id', auth()->id()),
            'driver' => fn ($query) => $query->with('driverProfile.documents')
                ->withAvg('ridesAsDriver as rating_average', 'rating')
                ->withCount(['ridesAsDriver as completed_trips_count' => fn ($rides) => $rides->where('status', RideStatus::Completed)]),
        ];
    }

    /**
     * A link to the photo of the driver, from the registration, for the passenger of this ride. It works for about two
     * hours and stays the same within the hour, so the phone keeps the picture instead of fetching it at every refresh.
     * The link has the path only: a phone reaches the server by its own address, which the server does not know.
     */
    public function driverPhotoUrl(): ?string
    {
        if (! $this->driver?->driverProfile?->documents->contains('type', DocumentType::Photo)) {
            return null;
        }

        return URL::temporarySignedRoute('api.v1.rides.driver.photo', now()->startOfHour()->addHours(2), ['ride' => $this->id], absolute: false);
    }

    public function loadForDisplay(): static
    {
        return $this->load(static::displayRelations());
    }

    public function isParticipant(User $user): bool
    {
        return $this->passenger_id === $user->id || $this->driver_id === $user->id;
    }
}
