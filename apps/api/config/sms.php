<?php

return [
    'endpoint' => env('MTC_SMS_ENDPOINT', 'http://int.mtcsms.com/sendsms.aspx'),
    'timeout' => (int) env('MTC_SMS_TIMEOUT', 10),
];
