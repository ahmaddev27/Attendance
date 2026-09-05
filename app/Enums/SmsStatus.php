<?php

namespace App\Enums;

enum SmsStatus: string
{
    case Sent = 'sent';
    case Failed = 'failed';
}
