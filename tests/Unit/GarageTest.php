<?php

namespace Tests\Unit;

use App\Enums\GarageType;
use App\Models\Garage;
use App\Models\Rental;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GarageTest extends TestCase
{
    use RefreshDatabase;

    public function test_constants(): void
    {
        $this->assertSame(999, Garage::MAX_GARAGES_PER_TYPE);
        $this->assertSame(3, Garage::CODE_DIGITS);
    }

    public function test_casts_with_spec_data(): void
    {
        $ev = Garage::factory()->create([
            'code' => 'E001', 'type' => 'mobil_listrik', 'daily_price' => 50000, 'charging_price_per_kwh' => 2500,
        ])->fresh();
        $car = Garage::factory()->create([
            'code' => 'C001', 'type' => 'mobil', 'daily_price' => 40000, 'charging_price_per_kwh' => null,
        ])->fresh();

        $this->assertSame(GarageType::MobilListrik, $ev->type);
        $this->assertSame(50000, $ev->daily_price);
        $this->assertSame(2500, $ev->charging_price_per_kwh);
        $this->assertFalse($ev->is_used);
        $this->assertNull($car->charging_price_per_kwh);
    }

    public function test_is_used_is_mass_assignable(): void
    {
        $garage = Garage::create([
            'code' => 'C001', 'type' => 'mobil', 'daily_price' => 40000, 'is_used' => true,
        ])->fresh();

        $this->assertTrue($garage->is_used);
    }

    public function test_same_type_can_have_different_prices(): void
    {
        $a = Garage::factory()->mobil()->create(['daily_price' => 40000]);
        $b = Garage::factory()->mobil()->create(['daily_price' => 55000]);

        $this->assertSame(GarageType::Mobil, $a->type);
        $this->assertSame(GarageType::Mobil, $b->type);
        $this->assertNotSame($a->daily_price, $b->daily_price);
    }

    public function test_duplicate_code_is_rejected(): void
    {
        Garage::factory()->create(['code' => 'C001']);

        $this->expectException(QueryException::class);
        Garage::factory()->create(['code' => 'C001']);
    }

    public function test_factory_states_per_type(): void
    {
        $s = Garage::factory()->sepeda()->create();
        $c = Garage::factory()->mobil()->create();
        $e = Garage::factory()->mobilListrik()->create();

        $this->assertStringStartsWith('S', $s->code);
        $this->assertStringStartsWith('C', $c->code);
        $this->assertStringStartsWith('E', $e->code);
        $this->assertSame(GarageType::Sepeda, $s->type);
        $this->assertNull($s->charging_price_per_kwh);
        $this->assertNull($c->charging_price_per_kwh);
        $this->assertNotNull($e->charging_price_per_kwh);
    }

    private function rentGarage(Garage $garage, string $start, string $end): void
    {
        Rental::factory()->for($garage)->between($start, $end)->create();
    }

    public function test_free_between_excludes_garages_with_an_overlapping_rental(): void
    {
        $booked = Garage::factory()->create();   // rented 21-24 Nov
        $free = Garage::factory()->create();     // never rented
        $this->rentGarage($booked, '2026-11-21', '2026-11-24');

        $ids = Garage::freeBetween(CarbonImmutable::parse('2026-11-22'), CarbonImmutable::parse('2026-11-25'))
            ->pluck('id');

        $this->assertTrue($ids->contains($free->id));
        $this->assertFalse($ids->contains($booked->id));
    }

    public function test_free_between_uses_half_open_range_on_both_edges(): void
    {
        $garage = Garage::factory()->create();
        $this->rentGarage($garage, '2026-11-21', '2026-11-24');

        // Starts on the existing end date: free.
        $this->assertTrue(Garage::freeBetween(CarbonImmutable::parse('2026-11-24'), CarbonImmutable::parse('2026-11-26'))->whereKey($garage->id)->exists());
        // Ends on the existing start date: free.
        $this->assertTrue(Garage::freeBetween(CarbonImmutable::parse('2026-11-18'), CarbonImmutable::parse('2026-11-21'))->whereKey($garage->id)->exists());
        // One day of overlap on either side: busy.
        $this->assertFalse(Garage::freeBetween(CarbonImmutable::parse('2026-11-23'), CarbonImmutable::parse('2026-11-25'))->whereKey($garage->id)->exists());
        $this->assertFalse(Garage::freeBetween(CarbonImmutable::parse('2026-11-19'), CarbonImmutable::parse('2026-11-22'))->whereKey($garage->id)->exists());
        // Fully containing the rental: busy.
        $this->assertFalse(Garage::freeBetween(CarbonImmutable::parse('2026-11-01'), CarbonImmutable::parse('2026-12-01'))->whereKey($garage->id)->exists());
    }

    public function test_free_between_counts_extensions_and_ignores_is_used(): void
    {
        $garage = Garage::factory()->create(['is_used' => false]);
        $original = Rental::factory()->for($garage)->between('2026-11-21', '2026-11-24')->create();
        Rental::factory()->extensionOf($original, 3)->create(); // 24-27 Nov

        $this->assertFalse(Garage::freeBetween(CarbonImmutable::parse('2026-11-25'), CarbonImmutable::parse('2026-11-26'))->whereKey($garage->id)->exists());

        // is_used = true but no rentals: still free (availability queries rentals only).
        $cached = Garage::factory()->create(['is_used' => true]);
        $this->assertTrue(Garage::freeBetween(CarbonImmutable::parse('2026-11-25'), CarbonImmutable::parse('2026-11-26'))->whereKey($cached->id)->exists());
    }

    public function test_free_between_chains_with_other_filters_and_accepts_mutable_carbon(): void
    {
        $sepeda = Garage::factory()->sepeda()->create();
        $mobil = Garage::factory()->mobil()->create();

        $ids = Garage::where('type', GarageType::Sepeda)->freeBetween(today(), today()->addDays(2))->pluck('id');

        $this->assertSame([$sepeda->id], $ids->all());
        $this->assertNotContains($mobil->id, $ids->all());
    }
}
