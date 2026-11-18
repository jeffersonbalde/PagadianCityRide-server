<?php

namespace App\Models;

use App\PlaceCategory;
use Database\Factories\PlaceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Place extends Model
{
    /** @use HasFactory<PlaceFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'area',
        'category',
        'source',
        'osm_id',
        'is_active',
        'latitude',
        'longitude',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category' => PlaceCategory::class,
            'is_active' => 'boolean',
            'latitude' => 'float',
            'longitude' => 'float',
        ];
    }
}
