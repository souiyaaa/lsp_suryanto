<?php

namespace Tests\Unit\Services;

use App\Models\Garage;
use App\Models\Rental;
use App\Services\GarageStatusService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GarageStatusServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-11-21'));
    }

    public function test_refresh_marks_a_garage_used_when_a_rental_covers_today(): void
    {
        $garage = Garage::factory()->create();
        Rental::factory()->for($garage)->create(); // today .. +3

        app(GarageStatusService::class)->refresh($garage);

        $this->assertTrue($garage->fresh()->is_used);
    }

    public function test_refresh_frees_a_garage_on_the_end_date_and_ignores_future_bookings(): void
    {
        $endedToday = Garage::factory()->create(['is_used' => true]);
        Rental::factory()->for($endedToday)->between(today()->subDays(3)->toDateString(), today()->toDateString())->create();
        $future = Garage::factory()->create(['is_used' => true]);
        Rental::factory()->for($future)->between(today()->addDay()->toDateString(), today()->addDays(4)->toDateString())->create();
        $service = app(GarageStatusService::class);

        $service->refresh($endedToday);
        $service->refresh($future);

        $this->assertFalse($endedToday->fresh()->is_used); // [start, end): free on the end date
        $this->assertFalse($future->fresh()->is_used);
    }

    public function test_refresh_counts_an_extension_that_covers_today(): void
    {
        $garage = Garage::factory()->create();
        $original = Rental::factory()->for($garage)->between(today()->subDays(3)->toDateString(), today()->toDateString())->create();
        Rental::factory()->extensionOf($original, 2)->create(); // today .. +2

        app(GarageStatusService::class)->refresh($garage);

        $this->assertTrue($garage->fresh()->is_used);
    }

    public function test_refresh_only_touches_the_given_garage(): void
    {
        $garage = Garage::factory()->create();
        $other = Garage::factory()->create(['is_used' => true]);
        Rental::factory()->for($garage)->create();

        app(GarageStatusService::class)->refresh($garage);

        $this->assertTrue($other->fresh()->is_used);
    }

    public function test_refresh_all_fixes_stale_flags_in_both_directions(): void
    {
        $shouldBeUsed = Garage::factory()->create(['is_used' => false]); // advance booking starts today
        Rental::factory()->for($shouldBeUsed)->create();
        $shouldBeFree = Garage::factory()->create(['is_used' => true]);  // rental ended yesterday
        Rental::factory()->for($shouldBeFree)->between(today()->subDays(4)->toDateString(), today()->subDay()->toDateString())->create();
        $neverRented = Garage::factory()->create(['is_used' => true]);

        app(GarageStatusService::class)->refreshAll();

        $this->assertTrue($shouldBeUsed->fresh()->is_used);
        $this->assertFalse($shouldBeFree->fresh()->is_used);
        $this->assertFalse($neverRented->fresh()->is_used);
    }
}
