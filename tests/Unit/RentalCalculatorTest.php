<?php

namespace Tests\Unit;

use App\Enums\RentalType;
use App\Services\Calculators\RentalCalculator;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class RentalCalculatorTest extends TestCase
{
    private RentalCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new RentalCalculator;
    }

    private function date(string $date): CarbonImmutable
    {
        return CarbonImmutable::parse($date);
    }

    public function test_tc_21_01_max_daily_days_is_days_in_the_start_month_minus_one(): void
    {
        $this->assertSame(29, $this->calculator->maxDailyDays($this->date('2026-11-21'))); // Nov = 30
        $this->assertSame(30, $this->calculator->maxDailyDays($this->date('2026-12-01'))); // Dec = 31
        $this->assertSame(27, $this->calculator->maxDailyDays($this->date('2027-02-10'))); // Feb 2027 = 28
        $this->assertSame(28, $this->calculator->maxDailyDays($this->date('2028-02-10'))); // leap year = 29
    }

    public function test_tc_21_02_daily_length_bounds(): void
    {
        $november = $this->date('2026-11-21');

        $this->assertFalse($this->calculator->isValidDailyLength($november, 0));
        $this->assertFalse($this->calculator->isValidDailyLength($november, -3));
        $this->assertTrue($this->calculator->isValidDailyLength($november, 1));
        $this->assertTrue($this->calculator->isValidDailyLength($november, 29));
        $this->assertFalse($this->calculator->isValidDailyLength($november, 30));
        $this->assertTrue($this->calculator->isValidDailyLength($this->date('2026-12-05'), 30));
        $this->assertFalse($this->calculator->isValidDailyLength($this->date('2026-12-05'), 31));
    }

    public function test_tc_23_01_end_date_for_daily_and_monthly(): void
    {
        $this->assertSame('2026-11-24', $this->calculator->calculateEndDate($this->date('2026-11-21'), RentalType::Harian, 3)->toDateString());
        // Examples from requirements §4.
        $this->assertSame('2026-12-21', $this->calculator->calculateEndDate($this->date('2026-11-21'), RentalType::Bulanan)->toDateString());
        $this->assertSame('2027-03-10', $this->calculator->calculateEndDate($this->date('2027-02-10'), RentalType::Bulanan)->toDateString());
        // Days in the START month: a December start is 31 days.
        $this->assertSame('2027-01-05', $this->calculator->calculateEndDate($this->date('2026-12-05'), RentalType::Bulanan)->toDateString());
        // The number of days is ignored for monthly.
        $this->assertSame('2026-12-21', $this->calculator->calculateEndDate($this->date('2026-11-21'), RentalType::Bulanan, 3)->toDateString());
    }

    public function test_tc_23_02_rent(): void
    {
        $this->assertSame(120000, $this->calculator->calculateRent(RentalType::Harian, 40000, 3));
        $this->assertSame(1000000, $this->calculator->calculateRent(RentalType::Bulanan, 40000, 30)); // 25 x price
        $this->assertSame(1000000, $this->calculator->calculateRent(RentalType::Bulanan, 40000, 28)); // days don't matter
    }

    public function test_tc_21_03_overlap_uses_half_open_ranges(): void
    {
        $existingStart = $this->date('2026-11-21');
        $existingEnd = $this->date('2026-11-24');

        $this->assertTrue($this->calculator->isOverlapping($existingStart, $existingEnd, $this->date('2026-11-23'), $this->date('2026-11-25')));
        $this->assertTrue($this->calculator->isOverlapping($existingStart, $existingEnd, $this->date('2026-11-01'), $this->date('2026-12-01')));
        $this->assertFalse($this->calculator->isOverlapping($existingStart, $existingEnd, $this->date('2026-11-24'), $this->date('2026-11-26'))); // starts on end
        $this->assertFalse($this->calculator->isOverlapping($existingStart, $existingEnd, $this->date('2026-11-18'), $this->date('2026-11-21'))); // ends on start
    }
}
