<?php

namespace App\Models;

use App\AppPlatform;
use Database\Factories\AppVersionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * The oldest version of the app that may still be used on one kind of phone. An
 * older app shows only "Update required". Without a version, every app may be used.
 */
class AppVersion extends Model
{
    /** @use HasFactory<AppVersionFactory> */
    use HasFactory;

    /**
     * How a version is written, like 1.0.2.
     */
    public const PATTERN = '/^\d{1,4}\.\d{1,4}\.\d{1,4}$/';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'platform',
        'minimum_version',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'platform' => AppPlatform::class,
        ];
    }

    public static function requiresUpdate(AppPlatform $platform, string $installed): bool
    {
        $minimum = self::query()->where('platform', $platform)->value('minimum_version');

        return $minimum !== null && version_compare($installed, $minimum, '<');
    }
}
