<?php

namespace App\Enums;

enum MaintenanceItemKind: string
{
    case Labor = 'labor';
    case Part = 'part';
    case Other = 'other';
}
