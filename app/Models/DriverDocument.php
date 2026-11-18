<?php

namespace App\Models;

use App\DocumentStatus;
use App\DocumentType;
use Database\Factories\DriverDocumentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DriverDocument extends Model
{
    /** @use HasFactory<DriverDocumentFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'type',
        'path',
        'status',
        'rejection_reason',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'path',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => DocumentType::class,
            'status' => DocumentStatus::class,
        ];
    }

    /**
     * @return BelongsTo<DriverProfile, $this>
     */
    public function driverProfile(): BelongsTo
    {
        return $this->belongsTo(DriverProfile::class);
    }
}
