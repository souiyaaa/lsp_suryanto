<?php
namespace App\Services;

use App\Enums\GarageType;
use App\Models\Garage;
use App\Services\Generators\GarageCodeGenerator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GarageBatchService
{
    public function __construct(private GarageCodeGenerator $garageCodeGenerator)
    {
    }

    /** @return Collection<int, Garage> */
    public function createBatch(GarageType $garageType, int $amount, int $dailyPrice, ?int $chargingPricePerKwh): Collection
    {
        return DB::transaction(function () use ($garageType, $amount, $dailyPrice, $chargingPricePerKwh) {
            $existingCount = Garage::ofType($garageType)->count();
            $remainingCapacity = $this->garageCodeGenerator->remainingCapacity($existingCount);

            if ($amount > $remainingCapacity) {
                throw ValidationException::withMessages([
                    'amount' => "Sisa kapasitas tipe ini hanya {$remainingCapacity} garasi.",
                ]);
            }

            $codes = $this->garageCodeGenerator->generateCodes($garageType, $existingCount, $amount);

            return collect($codes)->map(fn (string $code) => Garage::create([
                'code' => $code,
                'type' => $garageType,
                'daily_price' => $dailyPrice,
                // Only Mobil Listrik garages have a charging price (FR-13).
                'charging_price_per_kwh' => $garageType->hasCharging() ? $chargingPricePerKwh : null,
            ]));
        });
    }
}