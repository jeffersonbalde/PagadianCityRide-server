<?php

namespace App;

enum PlaceCategory: string
{
    /** A barangay, purok or sitio. */
    case Area = 'area';
    case Mall = 'mall';
    case Hospital = 'hospital';
    case School = 'school';
    case Church = 'church';
    case Market = 'market';
    case Terminal = 'terminal';
    case Government = 'government';
    case Other = 'other';

    /**
     * The category of a place in OpenStreetMap, or null when it is not a place a passenger goes to.
     *
     * @param  array<string, string>  $tags
     */
    public static function fromOpenStreetMapTags(array $tags): ?self
    {
        $amenity = $tags['amenity'] ?? null;

        return match (true) {
            ($tags['shop'] ?? null) === 'mall' => self::Mall,
            in_array($amenity, ['hospital', 'clinic'], true) => self::Hospital,
            in_array($amenity, ['school', 'college', 'university', 'kindergarten'], true) => self::School,
            $amenity === 'place_of_worship' => self::Church,
            $amenity === 'marketplace', ($tags['shop'] ?? null) === 'supermarket' => self::Market,
            in_array($amenity, ['bus_station', 'ferry_terminal'], true), ($tags['aeroway'] ?? null) === 'aerodrome' => self::Terminal,
            in_array($amenity, ['townhall', 'police', 'courthouse', 'fire_station'], true) => self::Government,
            in_array($tags['place'] ?? null, ['suburb', 'neighbourhood', 'village', 'quarter', 'hamlet'], true) => self::Area,
            in_array($tags['tourism'] ?? null, ['attraction', 'viewpoint', 'museum', 'hotel'], true) => self::Other,
            default => null,
        };
    }
}
