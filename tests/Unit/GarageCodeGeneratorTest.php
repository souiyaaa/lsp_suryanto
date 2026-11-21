<?php

namespace Tests\Unit;

use App\Enums\GarageType;
use App\Services\Generators\GarageCodeGenerator;
use PHPUnit\Framework\TestCase;

class GarageCodeGeneratorTest extends TestCase
{
    public function test_tc_10_01_remaining_capacity(): void
    {
        $generator = new GarageCodeGenerator;

        $this->assertSame(999, $generator->remainingCapacity(0));
        $this->assertSame(994, $generator->remainingCapacity(5));
        $this->assertSame(0, $generator->remainingCapacity(999));
    }

    public function test_tc_09_01_codes_continue_after_the_existing_count_with_the_type_prefix(): void
    {
        $generator = new GarageCodeGenerator;

        $this->assertSame(['C003', 'C004', 'C005'], $generator->generateCodes(GarageType::Mobil, 2, 3));
        $this->assertSame(['S001'], $generator->generateCodes(GarageType::Sepeda, 0, 1));
        $this->assertSame(['E010', 'E011'], $generator->generateCodes(GarageType::MobilListrik, 9, 2));
    }

    public function test_tc_09_02_last_code_is_999(): void
    {
        $this->assertSame(['C998', 'C999'], (new GarageCodeGenerator)->generateCodes(GarageType::Mobil, 997, 2));
    }
}
