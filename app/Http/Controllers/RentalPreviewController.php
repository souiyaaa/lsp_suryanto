<?php

namespace App\Http\Controllers;

use App\Enums\GarageType;
use App\Enums\RentalType;
use App\Http\Requests\AvailableGaragesRequest;
use App\Http\Requests\ChargingPreviewRequest;
use App\Http\Requests\RentalPreviewRequest;
use App\Models\Garage;
use App\Services\AvailabilityService;
use App\Services\Calculators\RentalCalculator;
use App\Services\ChargingService;
use App\Services\RentalService;
use App\Support\DateFormatter;
use App\Support\Rupiah;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** JSON for the New Rental and Bayar Pengecasan forms. */
class RentalPreviewController extends Controller
{
    public function __construct(
        private AvailabilityService $availabilityService,
        private RentalService $rentalService,
        private RentalCalculator $rentalCalculator,
        private ChargingService $chargingService,
    ) {
    }

    public function garageTypes(Request $request): JsonResponse
    {
        $vehicle = $request->user()->vehicles()->findOrFail($request->query('vehicle_id'));
        $compatibleTypes = collect(GarageType::cases())
            ->filter(fn (GarageType $garageType) => $garageType->allowsVehicle($vehicle->type))
            ->map(fn (GarageType $garageType) => ['value' => $garageType->value, 'label' => $garageType->label()])
            ->values();

        return $this->successResponse($compatibleTypes);
    }

    public function availableGarages(AvailableGaragesRequest $request): JsonResponse
    {
        $startDate = CarbonImmutable::parse($request->validated('start_date'));
        $endDate = $this->rentalCalculator->calculateEndDate(
            $startDate,
            RentalType::from($request->validated('rental_type')),
            $request->validated('number_of_days')
        );
        $freeGarages = $this->availabilityService->findFreeGarages(GarageType::from($request->validated('garage_type')), $startDate, $endDate);

        return $this->successResponse($freeGarages->map(fn (Garage $garage) => [
            'id' => $garage->id,
            'code' => $garage->code,
            'daily_price' => $garage->daily_price,
            'daily_price_label' => Rupiah::format($garage->daily_price),
        ])->values());
    }

    public function rentalPreview(RentalPreviewRequest $request): JsonResponse
    {
        $vehicle = $request->user()->vehicles()->findOrFail($request->validated('vehicle_id'));
        $garage = Garage::findOrFail($request->validated('garage_id'));
        $preview = $this->rentalService->previewRental($vehicle, $garage, $request->validated());

        return $this->successResponse([
            'end_date' => DateFormatter::format($preview['end_date']),
            'number_of_days' => $preview['number_of_days'],
            'rent' => Rupiah::format($preview['rent']),
            'charging' => $preview['charging'],
            'total' => Rupiah::format($preview['total']),
        ]);
    }

    public function chargingPreview(ChargingPreviewRequest $request): JsonResponse
    {
        $rental = $request->user()->findOwnRental((int) $request->validated('rental_id'));

        $activeSegment = $this->chargingService->findActiveSegment($rental) ?? abort(404);
        $preview = $this->chargingService->previewCharging($activeSegment->vehicle, $activeSegment->garage, (float) $request->validated('current_kwh'));

        return $this->successResponse([
            'kwh_needed' => $preview['kwh_needed'],
            'cost' => Rupiah::format($preview['cost']),
        ]);
    }
}
