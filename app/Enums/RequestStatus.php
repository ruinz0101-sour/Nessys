<?php

namespace App\Enums;

enum RequestStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Denied = 'denied';
    case Cancelled = 'cancelled';
}
