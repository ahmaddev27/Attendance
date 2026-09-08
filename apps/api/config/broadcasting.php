<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Broadcaster
    |--------------------------------------------------------------------------
    |
    | This option controls the default broadcaster that will be used by the
    | framework when an event needs to be broadcast. TAQAT ships with Reverb
    | (self-hosted, no third-party dependency); set BROADCAST_CONNECTION=null
    | in .env to disable realtime and fall back to database-only notifications.
    |
    | Supported: "reverb", "log", "null"
    |
    */

    'default' => env('BROADCAST_CONNECTION', 'null'),

    /*
    |--------------------------------------------------------------------------
    | Broadcast Connections
    |--------------------------------------------------------------------------
    |
    | Reverb speaks the Pusher protocol, so it's configured under the
    | "pusher" driver — Laravel Echo (pusher-js) on the frontend can connect
    | without knowing it's actually Reverb.
    |
    */

    'connections' => [

        'reverb' => [
            'driver' => 'reverb',
            'key' => env('REVERB_APP_KEY'),
            'secret' => env('REVERB_APP_SECRET'),
            'app_id' => env('REVERB_APP_ID'),
            'options' => [
                'host' => env('REVERB_HOST', 'reverb'),
                'port' => (int) env('REVERB_SERVER_PORT', 8080),
                'scheme' => env('REVERB_SCHEME', 'http'),
                'useTLS' => env('REVERB_SCHEME', 'http') === 'https',
            ],
            'client_options' => [
                // Passed into pusher-js on the server side (used when the
                // app itself broadcasts to Reverb, e.g., queue workers).
            ],
        ],

        'log' => [
            'driver' => 'log',
        ],

        'null' => [
            'driver' => 'null',
        ],

    ],

];
