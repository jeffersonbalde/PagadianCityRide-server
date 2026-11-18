<?php

namespace App\Models;

use Database\Factories\EmergencyContactFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A person a passenger trusts. When the passenger sends an emergency alert, this
 * person gets a text message with where the passenger is.
 */
class EmergencyContact extends Model
{
    /** @use HasFactory<EmergencyContactFactory> */
    use HasFactory;

    /**
     * The most contacts one person can have.
     */
    public const MAX_PER_USER = 5;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'name',
        'relationship',
        'phone',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
