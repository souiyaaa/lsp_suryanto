<?php

namespace Tests\Unit;

use App\Services\Calculators\ChargingCalculator;
use PHPUnit\Framework\TestCase;

class ChargingCalculatorTest extends TestCase
{
    public function test_tc_34_01_valid_current_charge_is_zero_up_to_below_max(): void
    {
        $calculator = new ChargingCalculator;

        $this->assertTrue($calculator->isValidCurrentCharge(0, 60));
        $this->assertTrue($calculator->isValidCurrentCharge(59.99, 60));
        $this->assertFalse($calculator->isValidCurrentCharge(60, 60));
        $this->assertFalse($calculator->isValidCurrentCharge(75, 60));
        $this->assertFalse($calculator->isValidCurrentCharge(-0.01, 60));
    }

    public function test_tc_34_02_kwh_needed_and_cost_match_the_spec_example(): void
    {
        $calculator = new ChargingCalculator;

        $kwhNeeded = $calculator->calculateKwhNeeded(60, 15);

        $this->assertEquals(45, $kwhNeeded);
        $this->assertSame(112500, $calculator->calculateCost($kwhNeeded, 2500));
    }

    public function test_tc_34_03_cost_is_rounded_to_whole_rupiah(): void
    {
        $calculator = new ChargingCalculator;

        $this->assertSame(3001, $calculator->calculateCost(1.2004, 2500)); // 3001.0
        $this->assertSame(3126, $calculator->calculateCost(1.2505, 2500)); // 3126.25 -> 3126
        $this->assertSame(1251, $calculator->calculateCost(0.5005, 2500)); // 1251.25 -> 1251
        $this->assertSame(0, $calculator->calculateCost(0, 2500));
    }
}
