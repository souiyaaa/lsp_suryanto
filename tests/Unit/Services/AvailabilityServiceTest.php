<?php

namespace Tests\Unit\Services;

use App\Enums\GarageType;
use App\Models\Garage;
use App\Models\Rental;
use App\Models\Vehicle;
use App\Services\AvailabilityService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AvailabilityServiceTest extends TestCase
{
    use RefreshDatabase;

    private function date(string $date): CarbonImmutable
    {
        return CarbonImmutable::parse($date);
    }

    public function test_find_free_garages_filters_by_type_and_orders_cheapest_first(): void
    {
        Garage::factory()->create(['daily_price' => 60000]);
        Garage::factory()->create(['daily_price' => 30000]);
        Garage::factory()->create(['daily_price' => 30000]);
        Garage::factory()->sepeda()->create();

        $prices = app(AvailabilityService::class)
            ->findFreeGarages(GarageType::Mobil, $this->date('2026-11-21'), $this->date('2026-11-24'))
            ->pluck('daily_price')->all();

        $this->assertSame([30000, 30000, 60000], $prices); // cheapest first; the sepeda garage is excluded
    }

    public function test_ties_on_price_are_ordered_by_code(): void
    {
        $first = Garage::factory()->create(['daily_price' => 30000]);
        $second = Garage::factory()->create(['daily_price' => 30000]);

        $codes = app(AvailabilityService::class)
            ->findFreeGarages(GarageType::Mobil, $this->date('2026-11-21'), $this->date('2026-11-24'))
            ->pluck('code')->all();

        $expected = collect([$first->code, $second->code])->sort()->values()->all();
        $this->assertSame($expected, $codes);
    }

    public function test_garage_with_an_overlapping_rental_is_not_listed_but_touching_ones_are(): void
    {
        $busy = Garage::factory()->create();
        $touchingAfter = Garage::factory()->create();
        $touchingBefore = Garage::factory()->create();
        Rental::factory()->for($busy)->between('2026-11-22', '2026-11-25')->create();
        Rental::factory()->for($touchingAfter)->between('2026-11-24', '2026-11-27')->create(); // starts on our end
        Rental::factory()->for($touchingBefore)->between('2026-11-18', '2026-11-21')->create(); // ends on our start

        $ids = app(AvailabilityService::class)
            ->findFreeGarages(GarageType::Mobil, $this->date('2026-11-21'), $this->date('2026-11-24'))
            ->pluck('id');

        $this->assertNotContains($busy->id, $ids->all());
        $this->assertContains($touchingAfter->id, $ids->all());
        $this->assertContains($touchingBefore->id, $ids->all());
    }

    public function test_is_garage_free(): void
    {
        $garage = Garage::factory()->create();
        Rental::factory()->for($garage)->between('2026-11-21', '2026-11-24')->create();
        $service = app(AvailabilityService::class);

        $this->assertFalse($service->isGarageFree($garage, $this->date('2026-11-23'), $this->date('2026-11-26')));
        $this->assertFalse($service->isGarageFree($garage, $this->date('2026-11-19'), $this->date('2026-11-22')));
        $this->assertTrue($service->isGarageFree($garage, $this->date('2026-11-24'), $this->date('2026-11-26')));
        $this->assertTrue($service->isGarageFree($garage, $this->date('2026-11-18'), $this->date('2026-11-21')));
        $this->assertTrue($service->isGarageFree(Garage::factory()->create(), $this->date('2026-11-21'), $this->date('2026-11-24')));
    }

    public function test_is_vehicle_free_ignores_other_vehicles_and_garages(): void
    {
        $rental = Rental::factory()->between('2026-11-21', '2026-11-24')->create();
        $service = app(AvailabilityService::class);

        $this->assertFalse($service->isVehicleFree($rental->vehicle, $this->date('2026-11-22'), $this->date('2026-11-25')));
        $this->assertTrue($service->isVehicleFree($rental->vehicle, $this->date('2026-11-24'), $this->date('2026-11-26')));
        $this->assertTrue($service->isVehicleFree(Vehicle::factory()->create(), $this->date('2026-11-22'), $this->date('2026-11-25')));
    }
}
