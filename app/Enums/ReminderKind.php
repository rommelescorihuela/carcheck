<?php

namespace App\Enums;

enum ReminderKind: string
{
    case Maintenance = 'maintenance';
    case Document = 'document';

    public function label(): string
    {
        return match ($this) {
            self::Maintenance => 'Mantenimiento',
            self::Document => 'Documento',
        };
    }
}
