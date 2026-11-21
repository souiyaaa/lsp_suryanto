<?php

namespace App\Enums;

use App\Traits\HasValues;

enum RentalType: string
{
    use HasValues;

    case Harian = 'harian';
    case Bulanan = 'bulanan';

    public function label(): string
    {
        return match ($this) {
            self::Harian => 'Harian',
            self::Bulanan => 'Bulanan',
        };
    }
}
