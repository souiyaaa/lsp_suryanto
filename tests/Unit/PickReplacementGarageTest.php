<?php

namespace Tests\Unit;

use App\Models\Garage;
use App\Services\ExtensionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PickReplacementGarageTest extends TestCase
{
    use RefreshDatabase;

    private ExtensionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(ExtensionService::class);
    }

    public function test_tc_30_01_none_free_returns_null(): void
    {
        $this->assertNull($this->service->pickReplacementGarage(collect()));
    }

    public function test_tc_30_02_cheapest_garage_is_picked(): void
    {
        $cheap = Garage::factory()->create(['daily_price' => 30000]);
        $mid = Garage::factory()->create(['daily_price' => 45000]);
        $pricey = Garage::factory()->create(['daily_price' => 50000]);

        $this->assertTrue($this->service->pickReplacementGarage(collect([$pricey, $mid, $cheap]))->is($cheap));
        $this->assertTrue($this->service->pickReplacementGarage(collect([$pricey]))->is($pricey));
    }

    public function test_tc_30_03_equal_prices_pick_one_of_them_at_random(): void
    {
        $cheapA = Garage::factory()->create(['daily_price' => 30000]);
        $cheapB = Garage::factory()->create(['daily_price' => 30000]);
        $pricey = Garage::factory()->create(['daily_price' => 50000]);

        $picked = collect(range(1, 30))
            ->map(fn () => $this->service->pickReplacementGarage(collect([$pricey, $cheapA, $cheapB]))->id)
            ->unique()->values();

        $this->assertNotContains($pricey->id, $picked->all());
        $this->assertEqualsCanonicalizing([$cheapA->id, $cheapB->id], $picked->all()); // both come up over 30 draws
    }
}
