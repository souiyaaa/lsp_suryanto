<?php

namespace App\Enums;

use App\Traits\HasValues;

enum VehicleType: string
{
    use HasValues;

    case Sepeda = 'sepeda';
    case SepedaMotor = 'sepeda_motor';
    case Mobil = 'mobil';
    case MobilListrik = 'mobil_listrik';

    public function requiresPlate(): bool
    {
        return $this !== self::Sepeda;
    }

    public function requiresBattery(): bool
    {
        return $this === self::MobilListrik;
    }

    public function label(): string
    {
        return match ($this) {
            self::Sepeda => 'Sepeda',
            self::SepedaMotor => 'Sepeda Motor',
            self::Mobil => 'Mobil',
            self::MobilListrik => 'Mobil Listrik',
        };
    }
}
