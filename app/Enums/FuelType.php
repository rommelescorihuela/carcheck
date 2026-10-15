<?php

namespace App\Enums;

enum FuelType: string
{
    case Gasoline = 'gasoline';
    case Diesel = 'diesel';
    case Cng = 'cng';
    case Electric = 'electric';
    case Hybrid = 'hybrid';
}
