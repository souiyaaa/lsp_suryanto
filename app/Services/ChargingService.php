<?php

namespace App\Services;

use App\Services\Calculators\ChargingCalculator;
use App\Models\ChargingTransaction;
use App\Models\Garage;
use App\Models\Rental;
use App\Models\Vehicle;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ChargingService
{
    public function __construct(private ChargingCalculator $chargingCalculator)
    {
    }

    /** @return array{kwh_needed: float, cost: int} */
    public function previewCharging(Vehicle $vehicle, Garage $garage, float $currentKwh): array
    {
        if (! $this->chargingCalculator->isValidCurrentCharge($currentKwh, $vehicle->max_battery_kwh)) {
            throw ValidationException::withMessages([
                'current_kwh' => "Daya saat ini harus 0 sampai di bawah {$vehicle->max_battery_kwh} kWh.",
            ]);
        }

        $kwhNeeded = $this->chargingCalculator->calculateKwhNeeded($vehicle->max_battery_kwh, $currentKwh);

        return [
            'kwh_needed' => $kwhNeeded,
            'cost' => $this->chargingCalculator->calculateCost($kwhNeeded, $garage->charging_price_per_kwh),
        ];
    }

    /** Bayar Pengecasan: only while the rental is active today, in an E garage. */
    public function addSession(Rental $originalRental, float $currentKwh, string $paymentProofUrl): ChargingTransaction
    {
        $activeSegment = $this->findActiveSegment($originalRental);

        if (! $activeSegment || ! $activeSegment->garage->type->hasCharging()) {
            throw new NotFoundHttpException();
        }

        $this->previewCharging($activeSegment->vehicle, $activeSegment->garage, $currentKwh);

        return $this->recordSession($activeSegment, $currentKwh, $paymentProofUrl);
    }

    public function recordSession(Rental $segment, float $currentKwh, string $paymentProofUrl): ChargingTransaction
    {
        return $segment->chargingTransactions()->create([
            'current_kwh' => $currentKwh,
            'payment_proof_url' => $paymentProofUrl,
        ]);
    }

    /** The segment (original or extension) covering today, so the session uses that garage's price. */
    public function findActiveSegment(Rental $originalRental): ?Rental
    {
        return Rental::inChainOf($originalRental)
            ->coveringDate(today())
            ->with('garage', 'vehicle')
            ->first();
    }

    /** Computed, never stored (decision 1); used by My Rentals and ReportService. */
    public function costOf(ChargingTransaction $chargingTransaction): int
    {
        $segment = $chargingTransaction->rental;
        $kwhNeeded = $this->chargingCalculator->calculateKwhNeeded($segment->vehicle->max_battery_kwh, $chargingTransaction->current_kwh);

        return $this->chargingCalculator->calculateCost($kwhNeeded, $segment->garage->charging_price_per_kwh);
    }
}