<?php

namespace App\Models;

use App\Enums\RentalType;
use Carbon\CarbonInterface;
use Database\Factories\RentalFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'user_id',
    'vehicle_id',
    'garage_id',
    'parent_rental_id',
    'rental_type',
    'start_date',
    'end_date',
    'payment_proof_url',
])]
class Rental extends Model
{
    /** @use HasFactory<RentalFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rental_type' => RentalType::class,
            'start_date' => 'immutable_date:Y-m-d',
            'end_date' => 'immutable_date:Y-m-d',
        ];
    }

    /**
     * start_date / end_date are plain dates. A Carbon value would be stored with a
     * "00:00:00" part, which breaks the string comparisons in the date scopes.
     */
    public function setAttribute($key, $value)
    {
        if (in_array($key, ['start_date', 'end_date'], true) && $value instanceof CarbonInterface) {
            $value = $value->toDateString();
        }

        return parent::setAttribute($key, $value);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** The penyewa who booked this rental (same column as user()). */
    public function penyewa(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function garage(): BelongsTo
    {
        return $this->belongsTo(Garage::class);
    }

    public function parentRental(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_rental_id');
    }

    public function extensions(): HasMany
    {
        return $this->hasMany(self::class, 'parent_rental_id');
    }

    public function chargingTransactions(): HasMany
    {
        return $this->hasMany(ChargingTransaction::class);
    }

    public function numberOfDays(): int
    {
        return (int) $this->start_date->diffInDays($this->end_date);
    }

    public function isExtension(): bool
    {
        return $this->parent_rental_id !== null;
    }

    /** [start, end): a rental ending on $date no longer covers it. */
    public function scopeCoveringDate(Builder $query, CarbonInterface $date): Builder
    {
        return $query->where('start_date', '<=', $date->toDateString())
            ->where('end_date', '>', $date->toDateString());
    }

    /** Overlap test: existing.start < end AND existing.end > start. */
    public function scopeOverlapping(Builder $query, CarbonInterface $startDate, CarbonInterface $endDate): Builder
    {
        return $query->where('start_date', '<', $endDate->toDateString())
            ->where('end_date', '>', $startDate->toDateString());
    }

    public function scopeInChainOf(Builder $query, Rental $originalRental): Builder
    {
        return $query->where(
            fn(Builder $chainQuery) => $chainQuery
                ->whereKey($originalRental->id)
                ->orWhere('parent_rental_id', $originalRental->id)
        );
    }

    /** Original rentals only; extensions point to them through parent_rental_id. */
    public function scopeOriginals(Builder $query): Builder
    {
        return $query->whereNull('parent_rental_id');
    }
}
