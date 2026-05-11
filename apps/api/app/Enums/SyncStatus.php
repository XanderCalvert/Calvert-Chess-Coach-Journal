<?php

namespace App\Enums;

enum SyncStatus: string
{
    case NeverSynced = 'never_synced';
    case Syncing     = 'syncing';
    case Synced      = 'synced';
    case Failed      = 'failed';
}
