<?php

namespace App\Enums;

enum SmartStatus: string
{
    case Ok = 'ok';
    case Warning = 'warning';
    case Failing = 'failing';
    case Unknown = 'unknown';
}
