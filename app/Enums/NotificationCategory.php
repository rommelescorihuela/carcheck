<?php

namespace App\Enums;

/**
 * Categorías de aviso que el usuario puede configurar. Son estables: añadir un tipo nuevo dentro
 * de una categoría no obliga a migrar las preferencias.
 */
enum NotificationCategory: string
{
    case System = 'system';
    case Billing = 'billing';
    case Maintenance = 'maintenance';
    case Documents = 'documents';
    case Reminders = 'reminders';

    public function label(): string
    {
        return match ($this) {
            self::System => 'Sistema',
            self::Billing => 'Facturación',
            self::Maintenance => 'Mantenimiento',
            self::Documents => 'Documentos',
            self::Reminders => 'Recordatorios',
        };
    }
}
