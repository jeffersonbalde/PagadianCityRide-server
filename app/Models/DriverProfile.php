<?php

namespace App\Models;

use App\DocumentType;
use App\DriverStatus;
use App\Support\Distance;
use Database\Factories\DriverProfileFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DriverProfile extends Model
{
    /** @use HasFactory<DriverProfileFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'tricycle_number',
        'plate_number',
        'franchise_number',
        'status',
        'is_online',
        'latitude',
        'longitude',
        'located_at',
        'rejection_reason',
        'submitted_at',
        'reviewed_at',
        'reviewed_by',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => DriverStatus::class,
            'is_online' => 'boolean',
            'latitude' => 'float',
            'longitude' => 'float',
            'located_at' => 'datetime',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * @return HasMany<DriverDocument, $this>
     */
    public function documents(): HasMany
    {
        return $this->hasMany(DriverDocument::class);
    }

    /**
     * Approved drivers who are online, have a recent location and are inside
     * the square around the point. Measure the exact distance afterwards.
     *
     * @param  Builder<DriverProfile>  $query
     */
    public function scopeAvailableNear(Builder $query, float $latitude, float $longitude, int $radiusMeters): void
    {
        $box = Distance::boundingBox($latitude, $longitude, $radiusMeters);

        $query->where('status', DriverStatus::Approved)
            ->where('is_online', true)
            ->where('located_at', '>=', now()->subMinutes(config('rides.driver_location_stale_after_minutes')))
            ->whereBetween('latitude', [$box['minLatitude'], $box['maxLatitude']])
            ->whereBetween('longitude', [$box['minLongitude'], $box['maxLongitude']]);
    }

    /**
     * The document types that were not uploaded yet.
     *
     * @return list<DocumentType>
     */
    public function missingDocumentTypes(): array
    {
        $uploaded = $this->documents->pluck('type')->all();

        return array_values(array_filter(
            DocumentType::cases(),
            fn (DocumentType $type): bool => ! in_array($type, $uploaded, true),
        ));
    }
}
