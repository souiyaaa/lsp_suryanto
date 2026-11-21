<?php

namespace Tests\Unit;

use App\Enums\VehicleType;
use Tests\TestCase;

class EnumValuesTest extends TestCase
{
    public function test_values_returns_database_values(): void
    {
        $this->assertSame(['sepeda', 'sepeda_motor', 'mobil', 'mobil_listrik'], VehicleType::values());
    }
}
