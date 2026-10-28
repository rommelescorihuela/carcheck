<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Transfer = 'transfer';
    case MobilePayment = 'mobile_payment';
    case Zelle = 'zelle';
    case Binance = 'binance';
    case Cash = 'cash';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Transfer => 'Transferencia',
            self::MobilePayment => 'Pago móvil',
            self::Zelle => 'Zelle',
            self::Binance => 'Binance',
            self::Cash => 'Efectivo',
            self::Other => 'Otro',
        };
    }
}
