<?php

namespace App\Enums;

use App\Traits\HasValues;

enum GarageType: string
{
    use HasValues;

    case Sepeda = 'sepeda';
    case Mobil = 'mobil';
    case MobilListrik = 'mobil_listrik';

    public function codePrefix(): string
    {
        return match ($this) {
            self::Sepeda => 'S',
            self::Mobil => 'C',
            self::MobilListrik => 'E',
        };
    }

    /** @return VehicleType[] */
    public function allowedVehicleTypes(): array
    {
        return match ($this) {
            self::Sepeda => [VehicleType::Sepeda, VehicleType::SepedaMotor],
            self::Mobil => [VehicleType::Mobil, VehicleType::MobilListrik],
            self::MobilListrik => [VehicleType::MobilListrik],
        };
    }

    public function allowsVehicle(VehicleType $vehicleType): bool
    {
        return in_array($vehicleType, $this->allowedVehicleTypes(), true);
    }

    public function hasCharging(): bool
    {
        return $this === self::MobilListrik;
    }

    public function label(): string
    {
        return match ($this) {
            self::Sepeda => 'Sepeda',
            self::Mobil => 'Mobil (Bensin)',
            self::MobilListrik => 'Mobil Listrik',
        };
    }
}
