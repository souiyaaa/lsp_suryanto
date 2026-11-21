<?php
namespace App\Services;

use App\Services\Calculators\RentalCalculator;
use App\Enums\RentalType;
use App\Models\Garage;
use App\Models\Rental;
use App\Models\User;
use App\Models\Vehicle;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RentalService
{
    public function __construct(
        private RentalCalculator $rentalCalculator,
        private AvailabilityService $availabilityService,
        private ChargingService $chargingService,
        private GarageStatusService $garageStatusService,
    ) {
    }

    /**
     * Used by the preview endpoint and by createRental, so the total shown is the total saved.
     *
     * @return array{start_date: CarbonImmutable, end_date: CarbonImmutable, number_of_days: int, rent: int, charging: ?array, total: int}
     */
    public function previewRental(Vehicle $vehicle, Garage $garage, array $rentalData): array
    {
        // First, so a non-electric car never reaches the charging maths.
        if (! $garage->type->allowsVehicle($vehicle->type)) {
            throw ValidationException::withMessages(['garage_id' => 'Tipe garasi tidak cocok dengan kendaraan.']);
        }

        if ($garage->type->hasCharging() && ! isset($rentalData['current_kwh'])) {
            throw ValidationException::withMessages(['current_kwh' => 'Isi daya baterai saat ini untuk garasi Mobil Listrik.']);
        }

        $startDate = CarbonImmutable::parse($rentalData['start_date']);
        $rentalType = RentalType::from($rentalData['rental_type']);
        $requestedDays = (int) ($rentalData['number_of_days'] ?? 0);

        if ($rentalType === RentalType::Harian && ! $this->rentalCalculator->isValidDailyLength($startDate, $requestedDays)) {
            $maxDays = $this->rentalCalculator->maxDailyDays($startDate);

            throw ValidationException::withMessages(['number_of_days' => "Sewa harian 1 sampai {$maxDays} hari."]);
        }

        $endDate = $this->rentalCalculator->calculateEndDate($startDate, $rentalType, $requestedDays);
        $numberOfDays = (int) $startDate->diffInDays($endDate);
        $rent = $this->rentalCalculator->calculateRent($rentalType, $garage->daily_price, $numberOfDays);
        $charging = $garage->type->hasCharging()
            ? $this->chargingService->previewCharging($vehicle, $garage, (float) $rentalData['current_kwh'])
            : null;

        return [
            'start_date' => $startDate,
            'end_date' => $endDate,
            'number_of_days' => $numberOfDays,
            'rent' => $rent,
            'charging' => $charging,
            'total' => $rent + ($charging['cost'] ?? 0),
        ];
    }

    public function createRental(User $penyewa, array $rentalData, string $paymentProofUrl): Rental
    {
        // Through the relation, so a penyewa can only rent with their own vehicle (NFR-02).
        $vehicle = $penyewa->vehicles()->findOrFail($rentalData['vehicle_id']);
        $garage = Garage::findOrFail($rentalData['garage_id']);
        $preview = $this->previewRental($vehicle, $garage, $rentalData);

        return DB::transaction(function () use ($penyewa, $vehicle, $garage, $rentalData, $preview, $paymentProofUrl) {
            $this->ensureBookable($vehicle, $garage, $preview['start_date'], $preview['end_date']);

            $rental = Rental::create([
                'user_id' => $penyewa->id,
                'vehicle_id' => $vehicle->id,
                'garage_id' => $garage->id,
                'rental_type' => $rentalData['rental_type'],
                'start_date' => $preview['start_date'],
                'end_date' => $preview['end_date'],
                'payment_proof_url' => $paymentProofUrl,
            ]);

            // E garage: the first charging session is created with the rental, same proof (FR-25).
            if ($garage->type->hasCharging()) {
                $this->chargingService->recordSession($rental, (float) $rentalData['current_kwh'], $paymentProofUrl);
            }

            $this->garageStatusService->refresh($garage);

            return $rental;
        });
    }

    private function ensureBookable(Vehicle $vehicle, Garage $garage, CarbonImmutable $startDate, CarbonImmutable $endDate): void
    {
        if (! $this->availabilityService->isGarageFree($garage, $startDate, $endDate)) {
            throw ValidationException::withMessages(['garage_id' => 'Garasi sudah terisi pada tanggal tersebut.']);
        }

        if (! $this->availabilityService->isVehicleFree($vehicle, $startDate, $endDate)) {
            throw ValidationException::withMessages(['vehicle_id' => 'Kendaraan sudah memiliki sewa pada tanggal tersebut.']);
        }
    }
}