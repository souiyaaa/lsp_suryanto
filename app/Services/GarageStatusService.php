<?php

namespace App\Services;

use App\Models\Garage;
use Illuminate\Support\Facades\DB;

class GarageStatusService
{
    /** Called inside the transaction that saves a rental or extension on this garage. */
    public function refresh(Garage $garage): void
    {
        $isUsedToday = $garage->rentals()->coveringDate(today())->exists();

        // forceFill because is_used is deliberately not in $fillable.
        $garage->forceFill(['is_used' => $isUsedToday])->save();
    }

    /** Run daily at 00:00 WIB, by the seeder, and before the demo. */
    public function refreshAll(): void
    {
        $today = today();

        DB::transaction(function () use ($today) {
            Garage::query()->update(['is_used' => false]);
            Garage::whereHas('rentals', fn ($rentalQuery) => $rentalQuery->coveringDate($today))
                ->update(['is_used' => true]);
        });
    }
}