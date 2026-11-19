<?php

return [

    /*
    |--------------------------------------------------------------------------
    | The public pages: privacy policy, terms and account deletion
    |--------------------------------------------------------------------------
    |
    | Google Play asks for these addresses. The contact email is shown on every
    | page, so put one that somebody reads in CONTACT_EMAIL. Without it the
    | pages ask people to write through the Google Play page of the app.
    |
    */

    'operator' => env('LEGAL_OPERATOR', 'PagadianCityRide'),

    'contact_email' => env('CONTACT_EMAIL'),

    'updated' => 'November 19, 2026',

];
