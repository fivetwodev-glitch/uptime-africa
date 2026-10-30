<?php

namespace App\Enums;

enum SyncStatus: string
{
    case NotApplicable = 'not_applicable';
    case Pending = 'pending';
    case Synced = 'synced';
    case Failed = 'failed';
}
