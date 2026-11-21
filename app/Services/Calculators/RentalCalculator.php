<?php
namespace App\Services\Calculators;

use App\Enums\RentalType;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

class RentalCalculator
{
    public const MONTHLY_PRICE_MULTIPLIER = 25;
    private const MIN_DAILY_DAYS = 1;

    public function maxDailyDays(CarbonInterface $startDate): int
    {
        return $startDate->daysInMonth - 1;
    }

    public function isValidDailyLength(CarbonInterface $startDate, int $numberOfDays): bool
    {
        return $numberOfDays >= self::MIN_DAILY_DAYS
            && $numberOfDays <= $this->maxDailyDays($startDate);
    }

    public function calculateEndDate(CarbonImmutable $startDate, RentalType $rentalType, ?int $numberOfDays = null): CarbonImmutable
    {
        $rentedDays = $rentalType === RentalType::Bulanan ? $startDate->daysInMonth : $numberOfDays;

        return $startDate->addDays($rentedDays);
    }

    public function calculateRent(RentalType $rentalType, int $dailyPrice, int $numberOfDays): int
    {
        if ($rentalType === RentalType::Bulanan) {
            return self::MONTHLY_PRICE_MULTIPLIER * $dailyPrice;
        }

        return $numberOfDays * $dailyPrice;
    }

    /**
     * Bookings occupy [start, end): a garage is free again on its end date,
     * so touching ranges (one ends the day the other starts) do not overlap.
     */
    public function isOverlapping(CarbonInterface $existingStart, CarbonInterface $existingEnd, CarbonInterface $newStart, CarbonInterface $newEnd): bool
    {
        return $existingStart->lt($newEnd) && $newStart->lt($existingEnd);
    }
}