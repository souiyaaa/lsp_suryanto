<?php

namespace Tests\Unit;

use App\Enums\GarageType;
use App\Enums\VehicleType;
use PHPUnit\Framework\TestCase;

class GarageTypeTest extends TestCase
{
    public function test_tc_20_01_compatibility_matrix_follows_requirements_2_1(): void
    {
        $matrix = [
            // garage type          => [sepeda, sepeda_motor, mobil, mobil_listrik]
            GarageType::Sepeda->value => [true, true, false, false],
            GarageType::Mobil->value => [false, false, true, true],
            GarageType::MobilListrik->value => [false, false, false, true],
        ];

        foreach ($matrix as $garageType => $expected) {
            $actual = array_map(
                fn (VehicleType $vehicleType) => GarageType::from($garageType)->allowsVehicle($vehicleType),
                [VehicleType::Sepeda, VehicleType::SepedaMotor, VehicleType::Mobil, VehicleType::MobilListrik]
            );

            $this->assertSame($expected, $actual, "garage type {$garageType}");
        }
    }

    public function test_tc_20_02_allowed_vehicle_types_per_garage_type(): void
    {
        $this->assertSame([VehicleType::Sepeda, VehicleType::SepedaMotor], GarageType::Sepeda->allowedVehicleTypes());
        $this->assertSame([VehicleType::Mobil, VehicleType::MobilListrik], GarageType::Mobil->allowedVehicleTypes());
        $this->assertSame([VehicleType::MobilListrik], GarageType::MobilListrik->allowedVehicleTypes());
    }

    public function test_tc_20_03_only_electric_garages_have_charging(): void
    {
        $this->assertFalse(GarageType::Sepeda->hasCharging());
        $this->assertFalse(GarageType::Mobil->hasCharging());
        $this->assertTrue(GarageType::MobilListrik->hasCharging());
    }

    public function test_tc_09_03_code_prefixes_and_labels(): void
    {
        $this->assertSame('S', GarageType::Sepeda->codePrefix());
        $this->assertSame('C', GarageType::Mobil->codePrefix());
        $this->assertSame('E', GarageType::MobilListrik->codePrefix());
        $this->assertSame('Mobil Listrik', GarageType::MobilListrik->label());
    }
}
