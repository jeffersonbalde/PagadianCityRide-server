<?php

namespace App\Models;

use Database\Factories\RideMessageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\URL;

/**
 * One chat message between the passenger and the driver of a ride. The chat keeps both phone numbers private.
 */
class RideMessage extends Model
{
    /** @use HasFactory<RideMessageFactory> */
    use HasFactory;

    /**
     * The longest message, in characters.
     */
    public const MAX_LENGTH = 500;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'ride_id',
        'sender_id',
        'body',
        'image_path',
        'read_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'read_at' => 'datetime',
        ];
    }

    /**
     * A link to the photo of the message that works for an hour, for the two people of the ride only. A photo
     * is shown with this link, so the phone does not need to send the sign in token with every picture. The
     * link has the path only: a phone reaches the server by its own address, which the server does not know.
     */
    public function imageUrl(): ?string
    {
        if ($this->image_path === null) {
            return null;
        }

        return URL::temporarySignedRoute('api.v1.rides.messages.image', now()->addHour(), ['ride' => $this->ride_id, 'message' => $this->id], absolute: false);
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
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }
}
