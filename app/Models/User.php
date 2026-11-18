<?php

namespace App\Models;

use App\UserRole;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'phone',
        'role',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Only admins open the admin panel in the browser.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return $this->role === UserRole::Admin;
    }

    /**
     * The application of a person who wants to drive a tricycle.
     *
     * @return HasOne<DriverProfile, $this>
     */
    public function driverProfile(): HasOne
    {
        return $this->hasOne(DriverProfile::class);
    }

    /**
     * The phones that get push notifications for this person.
     *
     * @return HasMany<DeviceToken, $this>
     */
    public function deviceTokens(): HasMany
    {
        return $this->hasMany(DeviceToken::class);
    }

    /**
     * The people who get a text message when this person sends an emergency alert.
     *
     * @return HasMany<EmergencyContact, $this>
     */
    public function emergencyContacts(): HasMany
    {
        return $this->hasMany(EmergencyContact::class);
    }

    /**
     * The rides this person asked for as a passenger.
     *
     * @return HasMany<Ride, $this>
     */
    public function ridesAsPassenger(): HasMany
    {
        return $this->hasMany(Ride::class, 'passenger_id');
    }

    /**
     * The rides this person drove as a driver.
     *
     * @return HasMany<Ride, $this>
     */
    public function ridesAsDriver(): HasMany
    {
        return $this->hasMany(Ride::class, 'driver_id');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
        ];
    }
}
