<?php

namespace App\Services\Generators;

use App\Enums\GarageType;
use App\Models\Garage;

class GarageCodeGenerator
{
    public function remainingCapacity(int $existingCount): int
    {
        return Garage::MAX_GARAGES_PER_TYPE - $existingCount;
    }

    /** @return string[] codes such as C003, C004, C005 */
    public function generateCodes(GarageType $garageType, int $existingCount, int $amount): array
    {
        return array_map(
            fn (int $garageNumber) => $garageType->codePrefix().str_pad((string) $garageNumber, Garage::CODE_DIGITS, '0', STR_PAD_LEFT),
            range($existingCount + 1, $existingCount + $amount)
        );
    }
}
