<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Matching
    |--------------------------------------------------------------------------
    |
    | A driver sees the passengers who wait within this many meters (in a
    | straight line), and a passenger sees the drivers who are online within the
    | same distance. A driver whose location is older than the number of minutes
    | below counts as gone.
    |
    */

    'search_radius_meters' => (int) env('RIDES_SEARCH_RADIUS_METERS', 3000),

    'driver_location_stale_after_minutes' => (int) env('RIDES_DRIVER_LOCATION_STALE_MINUTES', 3),

    /*
    |--------------------------------------------------------------------------
    | Requests
    |--------------------------------------------------------------------------
    |
    | A request that no driver picks up within this many minutes expires, and
    | a tricycle carries at most this many people.
    |
    */

    'request_expires_after_minutes' => (int) env('RIDES_REQUEST_EXPIRES_MINUTES', 10),

    'max_passengers' => 4,

    /*
    |--------------------------------------------------------------------------
    | Alerts
    |--------------------------------------------------------------------------
    |
    | A new request is sent to this many of the closest free drivers, so that
    | the whole town does not get a push notification for one passenger.
    |
    */

    'notify_nearest_drivers' => (int) env('RIDES_NOTIFY_NEAREST_DRIVERS', 10),

];
