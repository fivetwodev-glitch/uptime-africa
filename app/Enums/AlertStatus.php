<?php

namespace App\Enums;

enum AlertStatus: string
{
    case Queued = 'queued';
    case Sent = 'sent';
    case Failed = 'failed';
}
