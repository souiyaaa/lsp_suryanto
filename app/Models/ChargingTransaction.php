<?php

namespace App\Models;

use Database\Factories\ChargingTransactionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One charging session (always charged to full). No price, user_id, vehicle_id
 * or garage_id columns (3NF): read them through rental, and compute kWh/cost.
 * rental_id must be the segment covering the session date; the service sets it,
 * never form input.
 */
#[Fillable(['rental_id', 'current_kwh', 'payment_proof_url'])]
class ChargingTransaction extends Model
{
    /** @use HasFactory<ChargingTransactionFactory> */
    use HasFactory;

    protected $table = 'charging_trx';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['current_kwh' => 'decimal:2'];
    }

    public function rental(): BelongsTo
    {
        return $this->belongsTo(Rental::class);
    }

    /** kWh needed = vehicle max capacity - current charge. */
    protected function kwhNeeded(): Attribute
    {
        return Attribute::get(fn (): float => round(
            (float) $this->rental->vehicle->max_battery_kwh - (float) $this->current_kwh,
            2,
        ));
    }

    /** Cost in whole Rupiah = kWh needed x the segment garage's charging price per kWh. */
    protected function cost(): Attribute
    {
        return Attribute::get(fn (): int => (int) round(
            $this->kwh_needed * $this->rental->garage->charging_price_per_kwh,
        ));
    }
}
