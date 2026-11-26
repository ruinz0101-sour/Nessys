<?php

namespace App\Enums;

enum RootType: string
{
    case Shared = 'shared';
    case Department = 'department';
    case Personal = 'personal';
}
