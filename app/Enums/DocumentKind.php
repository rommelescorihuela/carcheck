<?php

namespace App\Enums;

enum DocumentKind: string
{
    case Insurance = 'insurance';
    case Inspection = 'inspection';
    case Tax = 'tax';
    case License = 'license';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Insurance => 'Seguro',
            self::Inspection => 'Revisión',
            self::Tax => 'Impuesto',
            self::License => 'Licencia',
            self::Other => 'Otro',
        };
    }
}
