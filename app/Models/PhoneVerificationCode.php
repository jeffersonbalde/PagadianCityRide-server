<?php

namespace App\Models;

use Database\Factories\PhoneVerificationCodeFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PhoneVerificationCode extends Model
{
    /** @use HasFactory<PhoneVerificationCodeFactory> */
    use HasFactory;

    public const MAX_ATTEMPTS = 5;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'phone',
        'code',
        'attempts',
        'expires_at',
        'consumed_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'code',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'code' => 'hashed',
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
        ];
    }

    /**
     * Codes that can still be used: not consumed, not expired, not locked.
     *
     * @param  Builder<PhoneVerificationCode>  $query
     */
    public function scopeUsable(Builder $query): void
    {
        $query->whereNull('consumed_at')
            ->where('expires_at', '>', now())
            ->where('attempts', '<', self::MAX_ATTEMPTS);
    }
}
