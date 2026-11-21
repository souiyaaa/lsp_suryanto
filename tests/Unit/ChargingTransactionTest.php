<?php

namespace Tests\Unit;

use App\Models\ChargingTransaction;
use App\Models\Garage;
use App\Models\Rental;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ChargingTransactionTest extends TestCase
{
    use RefreshDatabase;

    private function makeSession(float $current = 15, float $max = 60, int $price = 2500): ChargingTransaction
    {
        $rental = Rental::factory()
            ->for(Garage::factory()->mobilListrik()->create(['charging_price_per_kwh' => $price]))
            ->create();
        $rental->vehicle->update(['max_battery_kwh' => $max]);

        return ChargingTransaction::factory()->for($rental)->create(['current_kwh' => $current]);
    }

    public function test_uses_explicit_table_name(): void
    {
        $this->assertSame('charging_trx', (new ChargingTransaction)->getTable());
    }

    public function test_current_kwh_cast_and_rental_relation(): void
    {
        $trx = $this->makeSession()->fresh();

        $this->assertSame('15.00', $trx->current_kwh);
        $this->assertInstanceOf(Rental::class, $trx->rental);
    }

    public function test_cost_is_computed_not_stored(): void
    {
        $trx = $this->makeSession(15, 60, 2500);

        $this->assertSame(45.0, $trx->kwh_needed);
        $this->assertSame(112500, $trx->cost);
    }

    public function test_cost_is_rounded_to_whole_rupiah(): void
    {
        $trx = $this->makeSession(15.25, 60, 2500); // 44.75 kWh x 2500 = 111875

        $this->assertSame(111875, $trx->cost);
        $this->assertSame(275, $this->makeSession(59.89, 60, 2500)->cost); // 0.11 x 2500 = 275
    }

    public function test_no_price_or_copied_foreign_key_columns(): void
    {
        $columns = Schema::getColumnListing('charging_trx');

        $this->assertEqualsCanonicalizing(
            ['id', 'rental_id', 'current_kwh', 'payment_proof_url', 'created_at', 'updated_at'],
            $columns,
        );
    }

    public function test_user_vehicle_garage_read_through_rental(): void
    {
        $this->makeSession();
        $trx = ChargingTransaction::with('rental.garage', 'rental.vehicle', 'rental.user')->first();

        $this->assertInstanceOf(Vehicle::class, $trx->rental->vehicle);
        $this->assertInstanceOf(Garage::class, $trx->rental->garage);
        $this->assertTrue($trx->rental->vehicle->user->is($trx->rental->user));
    }
}
