<?php

return [

    /*
    |--------------------------------------------------------------------------
    | OpenStreetMap
    |--------------------------------------------------------------------------
    |
    | The places of Pagadian City come from OpenStreetMap through the Overpass
    | API. The data is free to use under the ODbL license, as long as the app
    | says "© OpenStreetMap contributors". Overpass wants a name for the app in
    | the user agent. The search is inside the relation of the city when its
    | number is set, and inside the box below when it is not.
    |
    */

    'overpass_url' => env('OVERPASS_URL', 'https://overpass-api.de/api/interpreter'),

    'user_agent' => env('OVERPASS_USER_AGENT', 'PagadianCityRide/1.0'),

    'relation_id' => env('PLACES_OSM_RELATION_ID'),

    'bounds' => [
        'south' => 7.74,
        'west' => 123.36,
        'north' => 7.92,
        'east' => 123.49,
    ],

];
