<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'sms' => [
        'driver' => env('SMS_DRIVER', 'log'),

        // Every text starts with this, and the telcos expect it to be the approved sender name (Semaphore asks for a
        // sample that starts with it). It falls back to the sender name, then to the name of the app.
        'prefix' => env('SMS_PREFIX') ?? env('SEMAPHORE_SENDER_NAME') ?? env('APP_NAME', 'Laravel'),

        // For trying the app on a phone before the SMS provider is set up: any 6-digit code signs in.
        // It only works while APP_ENV is "local", so it can never open a production server.
        'accept_any_code' => (bool) env('SMS_ACCEPT_ANY_CODE', false),
    ],

    // The reviewers of Google Play cannot read an inbox, so one account of ours signs in with a fixed code and no email
    // is sent to it. Both values must be set, and only this one address works this way. Remove them when the review is done.
    'review_account' => [
        'email' => env('REVIEW_ACCOUNT_EMAIL'),
        'code' => env('REVIEW_ACCOUNT_CODE'),
    ],

    'semaphore' => [
        'key' => env('SEMAPHORE_API_KEY'),
        'sender_name' => env('SEMAPHORE_SENDER_NAME'),
        'base_url' => env('SEMAPHORE_BASE_URL', 'https://api.semaphore.co/api/v4'),
    ],

    // An Android phone of our own that texts with its SIM, through the "SMS Gateway for Android" app in Cloud Server
    // mode. The username and the password are the ones the app shows after Sign Up.
    'android_sms' => [
        'base_url' => env('ANDROID_SMS_URL', 'https://api.sms-gate.app/3rdparty/v1'),
        'username' => env('ANDROID_SMS_USERNAME'),
        'password' => env('ANDROID_SMS_PASSWORD'),
        // A text the phone did not pick up in this time is dropped, so an old login code is not sent late.
        'ttl_seconds' => (int) env('ANDROID_SMS_TTL_SECONDS', 300),
    ],

    'push' => [
        'driver' => env('PUSH_DRIVER', 'log'),
    ],

    'expo' => [
        'access_token' => env('EXPO_ACCESS_TOKEN'),
        'url' => env('EXPO_PUSH_URL', 'https://exp.host/--/api/v2/push/send'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
