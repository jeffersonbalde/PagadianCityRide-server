<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Road routes
    |--------------------------------------------------------------------------
    |
    | The line the map draws from the pick-up point to the destination follows
    | the roads. It comes from an OSRM server, which works on OpenStreetMap
    | data. The public server is for trying things out: for the real app run
    | an OSRM server of your own and put its address in OSRM_URL. A route does
    | not change, so each one is kept for a while and asked for only once.
    |
    */

    'osrm_url' => env('OSRM_URL', 'https://router.project-osrm.org'),

    'user_agent' => env('OSRM_USER_AGENT', 'PagadianCityRide/1.0'),

    // A trip in the city is short. A longer one is a mistake or someone trying the server.
    'max_distance_meters' => 30000,

    'cache_hours' => 24,

];
