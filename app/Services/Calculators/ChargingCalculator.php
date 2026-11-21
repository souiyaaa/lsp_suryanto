<?php
namespace App\Services\Calculators;

class ChargingCalculator
{
    private const MIN_CURRENT_KWH = 0;

    public function isValidCurrentCharge(float $currentKwh, float $maxCapacityKwh): bool
    {
        return $currentKwh >= self::MIN_CURRENT_KWH && $currentKwh < $maxCapacityKwh;
    }

    /** The system always charges to full, so only the missing part is billed. */
    public function calculateKwhNeeded(float $maxCapacityKwh, float $currentKwh): float
    {
        return $maxCapacityKwh - $currentKwh;
    }

    public function calculateCost(float $kwhNeeded, int $chargingPricePerKwh): int
    {
        return (int) round($kwhNeeded * $chargingPricePerKwh);
    }
}