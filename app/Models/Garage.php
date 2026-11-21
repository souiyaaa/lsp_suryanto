<?php

namespace App\Models;

use App\Enums\GarageType;
use Carbon\CarbonInterface;
use Database\Factories\GarageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code', 'type', 'daily_price', 'charging_price_per_kwh', 'is_used'])]
class Garage extends Model
{
    /** @use HasFactory<GarageFactory> */
    use HasFactory;

    public const MAX_GARAGES_PER_TYPE = 999;

    public const CODE_DIGITS = 3;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => GarageType::class,
            'is_used' => 'boolean',
            'daily_price' => 'integer',
            'charging_price_per_kwh' => 'integer',
        ];
    }

    public function rentals(): HasMany
    {
        return $this->hasMany(Rental::class);
    }

    public function scopeOfType(Builder $query, GarageType $garageType): Builder
    {
        return $query->where('type', $garageType);
    }

    /** Availability (FR-21): no rental on this garage overlaps [start, end). */
    public function scopeFreeBetween(Builder $query, CarbonInterface $startDate, CarbonInterface $endDate): Builder
    {
        return $query->whereDoesntHave(
            'rentals',
            fn (Builder $rentalQuery) => $rentalQuery->overlapping($startDate, $endDate)
        );
    }
}
