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

    /*
    | Meta (Facebook) WhatsApp Cloud API.
    | The WhatsappGateway binding in AppServiceProvider picks the real
    | Meta gateway when access_token + phone_number_id are set and
    | `fake=false`; otherwise the FakeWhatsappGateway is used (also
    | always used in the `testing` env). `endpoint` overrides the Graph
    | base URL used to build the /{phone_number_id}/messages URL — leave
    | blank to use the default v20.0 production endpoint.
    */
    'whatsapp' => [
        'access_token' => env('WHATSAPP_ACCESS_TOKEN'),
        'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),
        'business_account_id' => env('WHATSAPP_BUSINESS_ACCOUNT_ID'),
        'endpoint' => env('WHATSAPP_ENDPOINT'),
        'timeout' => (int) env('WHATSAPP_TIMEOUT', 10),
        'fake' => (bool) env('WHATSAPP_FAKE', false),
    ],

    /*
    | Expo Push Notifications.
    | Anonymous sends work without any config — Expo accepts the public
    | ExponentPushToken scheme with no auth. Set `access_token` only if
    | you opt into Expo's "Enhanced Security" mode (recommended for
    | production so third parties can't spoof pushes). `fake` routes
    | every send to the FakePushGateway instead of Expo — the default
    | for local dev + CI so tests never call an external service.
    */
    'push' => [
        'access_token' => env('EXPO_ACCESS_TOKEN'),
        'fake' => (bool) env('PUSH_FAKE', false),
    ],

];
