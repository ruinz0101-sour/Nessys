<?php

namespace App\Enums;

enum RaidState: string
{
    case Healthy = 'healthy';
    case Degraded = 'degraded';
    case Rebuilding = 'rebuilding';
    case Failed = 'failed';
    case Unknown = 'unknown';
}
