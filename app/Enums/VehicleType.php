<?php

namespace App\Enums;

enum VehicleType: string
{
    case Car = 'car';
    case Motorcycle = 'motorcycle';
    case Truck = 'truck';
    case Other = 'other';
}
