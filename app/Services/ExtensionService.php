<?php
namespace App\Services;

use App\Services\Calculators\RentalCalculator;
use App\Enums\RentalType;
use App\Models\Garage;
use App\Models\Rental;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ExtensionService
{
    public function __construct(
        private RentalCalculator $rentalCalculator,
        private AvailabilityService $availabilityService,
        private GarageStatusService $garageStatusService,
    ) {
    }

    /** FR-27: original rentals whose chain (original or any extension) covers today. */
    public function activeRentalsToday(): Collection
    {
        $today = today();

        return Rental::originals()
            ->where(fn (Builder $chainQuery) => $chainQuery
                ->coveringDate($today)
                ->orWhereHas('extensions', fn (Builder $extensionQuery) => $extensionQuery->coveringDate($today)))
            ->with('penyewa', 'vehicle', 'garage', 'extensions.garage')
            ->orderBy('start_date')
            ->get();
    }

    /** The segment with the latest end date: the next extension starts there. */
    public function currentSegment(Rental $originalRental): Rental
    {
        return Rental::inChainOf($originalRental)->with('garage')->orderByDesc('end_date')->firstOrFail();
    }

    /** @return array{start_date: CarbonImmutable, end_date: CarbonImmutable, number_of_days: int, garage: Garage, is_slot_changed: bool, fee: int} */
    public function previewExtension(Rental $originalRental, RentalType $rentalType, ?int $requestedDays): array
    {
        $this->ensureActiveToday($originalRental);

        $currentSegment = $this->currentSegment($originalRental);
        $startDate = $currentSegment->end_date;

        if ($rentalType === RentalType::Harian && ! $this->rentalCalculator->isValidDailyLength($startDate, $requestedDays ?? 0)) {
            $maxDays = $this->rentalCalculator->maxDailyDays($startDate);

            throw ValidationException::withMessages(['number_of_days' => "Perpanjangan harian 1 sampai {$maxDays} hari."]);
        }

        $endDate = $this->rentalCalculator->calculateEndDate($startDate, $rentalType, $requestedDays);

        if (! $this->availabilityService->isVehicleFree($originalRental->vehicle, $startDate, $endDate)) {
            throw ValidationException::withMessages(['rental_type' => 'Kendaraan sudah memiliki sewa lain pada periode tersebut.']);
        }

        $garage = $this->resolveGarage($currentSegment->garage, $startDate, $endDate);
        $numberOfDays = (int) $startDate->diffInDays($endDate);

        return [
            'start_date' => $startDate,
            'end_date' => $endDate,
            'number_of_days' => $numberOfDays,
            'garage' => $garage,
            'is_slot_changed' => $garage->isNot($currentSegment->garage),
            'fee' => $this->rentalCalculator->calculateRent($rentalType, $garage->daily_price, $numberOfDays),
        ];
    }

    public function extend(Rental $originalRental, array $extensionData, string $paymentProofUrl): Rental
    {
        return DB::transaction(function () use ($originalRental, $extensionData, $paymentProofUrl) {
            // Recomputed inside the transaction: the slot may have been taken since the preview.
            $preview = $this->previewExtension(
                $originalRental,
                RentalType::from($extensionData['rental_type']),
                $extensionData['number_of_days'] ?? null
            );
            $confirmedGarage = $this->ensureConfirmedGarage($preview, (int) $extensionData['garage_id']);

            $extension = Rental::create([
                'user_id' => $originalRental->user_id,
                'vehicle_id' => $originalRental->vehicle_id,
                'garage_id' => $confirmedGarage->id,
                'parent_rental_id' => $originalRental->id,
                'rental_type' => $extensionData['rental_type'],
                'start_date' => $preview['start_date'],
                'end_date' => $preview['end_date'],
                'payment_proof_url' => $paymentProofUrl,
            ]);

            $this->garageStatusService->refresh($confirmedGarage);

            return $extension;
        });
    }

    /** BR-13: cheapest daily price; random among equal prices. */
    public function pickReplacementGarage(Collection $freeGarages): ?Garage
    {
        if ($freeGarages->isEmpty()) {
            return null;
        }

        $cheapestPrice = $freeGarages->min('daily_price');

        return $freeGarages->where('daily_price', $cheapestPrice)->random();
    }

    private function resolveGarage(Garage $currentGarage, CarbonImmutable $startDate, CarbonImmutable $endDate): Garage
    {
        if ($this->availabilityService->isGarageFree($currentGarage, $startDate, $endDate)) {
            return $currentGarage;
        }

        $freeGarages = $this->availabilityService->findFreeGarages($currentGarage->type, $startDate, $endDate);
        $replacementGarage = $this->pickReplacementGarage($freeGarages);

        if (! $replacementGarage) {
            throw ValidationException::withMessages(['rental_type' => 'Tidak ada garasi tipe ini yang kosong untuk periode tersebut.']);
        }

        return $replacementGarage;
    }

    /**
     * The admin confirmed a garage in the popup. Ties are random, so a second preview may pick a
     * different garage at the same price; that is still a valid choice and is accepted.
     */
    private function ensureConfirmedGarage(array $preview, int $confirmedGarageId): Garage
    {
        $confirmedGarage = Garage::findOrFail($confirmedGarageId);

        if ($confirmedGarage->is($preview['garage'])) {
            return $confirmedGarage;
        }

        $isEquallyCheapest = $preview['is_slot_changed']
            && $confirmedGarage->type === $preview['garage']->type
            && $confirmedGarage->daily_price === $preview['garage']->daily_price
            && $this->availabilityService->isGarageFree($confirmedGarage, $preview['start_date'], $preview['end_date']);

        if (! $isEquallyCheapest) {
            throw ValidationException::withMessages(['garage_id' => 'Slot sudah berubah. Silakan cek ulang perpanjangan.']);
        }

        return $confirmedGarage;
    }

    private function ensureActiveToday(Rental $originalRental): void
    {
        // Extensions are reached through their original rental, never directly.
        if ($originalRental->isExtension()) {
            throw new NotFoundHttpException();
        }

        $isActiveToday = Rental::inChainOf($originalRental)->coveringDate(today())->exists();

        if (! $isActiveToday) {
            throw ValidationException::withMessages(['rental_type' => 'Sewa ini tidak aktif hari ini.']);
        }
    }
}