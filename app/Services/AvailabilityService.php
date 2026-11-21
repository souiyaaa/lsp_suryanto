<?php

namespace App\Services;

use App\Enums\GarageType;
use App\Models\Garage;
use App\Models\Vehicle;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/** Every [start, end) question goes through here; the SQL lives in the Rental and Garage scopes. */
class AvailabilityService
{
    /** Cheapest first, so the penyewa sees prices in order and extensions can take the first one. */
    public function findFreeGarages(GarageType $garageType, CarbonImmutable $startDate, CarbonImmutable $endDate): Collection
    {
        return Garage::ofType($garageType)
            ->freeBetween($startDate, $endDate)
            ->orderBy('daily_price')
            ->orderBy('code')
            ->get();
    }

    public function isGarageFree(Garage $garage, CarbonImmutable $startDate, CarbonImmutable $endDate): bool
    {
        return ! $garage->rentals()
            ->overlapping($startDate, $endDate)
            ->exists();
    }

    public function isVehicleFree(Vehicle $vehicle, CarbonImmutable $startDate, CarbonImmutable $endDate): bool
    {
        return ! $vehicle->rentals()
            ->overlapping($startDate, $endDate)
            ->exists();
    }
}