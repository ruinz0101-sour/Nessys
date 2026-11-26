<?php

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case DeptManager = 'dept_manager';
    case User = 'user';
}
