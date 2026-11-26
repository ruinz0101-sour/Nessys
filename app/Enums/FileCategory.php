<?php

namespace App\Enums;

enum FileCategory: string
{
    case Image = 'image';
    case Video = 'video';
    case Document = 'document';
    case Other = 'other';
}
