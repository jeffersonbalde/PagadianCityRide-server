<?php

namespace App\Models;

use Database\Factories\DeviceTokenFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A phone that can get push notifications for a user. The token is the Expo
 * push token that the app asks for, like `ExponentPushToken[xxxx]`.
 */
class DeviceToken extends Model
{
    /** @use HasFactory<DeviceTokenFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'token',
        'platform',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
