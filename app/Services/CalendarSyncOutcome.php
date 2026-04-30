<?php

namespace App\Services;

enum CalendarSyncOutcome: string
{
    case Synced = 'synced';
    case NotConnected = 'not_connected';
    case MissingEventDate = 'missing_event_date';
    case Failed = 'failed';
}
