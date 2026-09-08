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
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    | MTC SMS gateway (Jordan Telecom).
    | Credentials live in env — the SmsGateway binding in
    | AppServiceProvider picks the real gateway when username/password
    | are set and `fake=false`, otherwise the FakeSmsGateway is used
    | (also always used in the `testing` env).
    */
    'mtc_sms' => [
        'username' => env('MTC_SMS_USERNAME'),
        'password' => env('MTC_SMS_PASSWORD'),
        'sender' => env('MTC_SMS_SENDER', 'TAQAT'),
        'endpoint' => env('MTC_SMS_ENDPOINT'),
        'timeout' => (int) env('MTC_SMS_TIMEOUT', 10),
        'fake' => (bool) env('MTC_SMS_FAKE', false),
    ],

];
