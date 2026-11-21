<?php

namespace Tests\Unit\Services;

use App\Models\ChargingTransaction;
use App\Models\Garage;
use App\Models\Rental;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\ChargingService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

class ChargingServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-11-21'));
    }

    private const URL = 'https://example.test/proof.jpg';

    private function car(int $max = 60): Vehicle
    {
        return Vehicle::factory()->mobilListrik()->create(['max_battery_kwh' => $max]);
    }

    private function electricGarage(int $pricePerKwh = 2500): Garage
    {
        return Garage::factory()->mobilListrik()->create(['charging_price_per_kwh' => $pricePerKwh]);
    }

    private function rentalOf(Vehicle $car, Garage $garage, ?string $start = null, ?string $end = null): Rental
    {
        $factory = Rental::factory()->for($car->user)->for($car, 'vehicle')->for($garage);

        return ($start ? $factory->between($start, $end) : $factory)->create();
    }

    public function test_preview_matches_the_spec_example(): void
    {
        $preview = app(ChargingService::class)->previewCharging($this->car(60), $this->electricGarage(2500), 15);

        $this->assertEquals(45, $preview['kwh_needed']);
        $this->assertSame(112500, $preview['cost']);
    }

    public function test_preview_rejects_a_charge_outside_zero_to_below_max(): void
    {
        $service = app(ChargingService::class);
        $car = $this->car(60);
        $garage = $this->electricGarage();

        foreach ([60, 75, -1] as $invalid) {
            try {
                $service->previewCharging($car, $garage, $invalid);
                $this->fail("{$invalid} kWh should be refused");
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('current_kwh', $e->errors());
            }
        }

        $this->assertSame(150000, $service->previewCharging($car, $garage, 0)['cost']); // empty battery: 60 kWh
    }

    public function test_add_session_records_it_on_the_active_rental(): void
    {
        $car = $this->car();
        $rental = $this->rentalOf($car, $this->electricGarage());

        $session = app(ChargingService::class)->addSession($rental, 20, self::URL);

        $this->assertSame($rental->id, $session->rental_id);
        $this->assertSame(self::URL, $session->payment_proof_url);
        $this->assertEquals(20, $session->fresh()->current_kwh);
    }

    public function test_session_attaches_to_the_extension_segment_covering_today(): void
    {
        $car = $this->car();
        $oldGarage = $this->electricGarage(2500);
        $newGarage = $this->electricGarage(4000); // the extension moved to a pricier charger
        $original = $this->rentalOf($car, $oldGarage, today()->subDays(3)->toDateString(), today()->toDateString());
        $extension = Rental::factory()->extensionOf($original, 3)->create(['garage_id' => $newGarage->id]);
        $service = app(ChargingService::class);

        $session = $service->addSession($original, 40, self::URL);

        $this->assertSame($extension->id, $session->rental_id);
        $this->assertSame($newGarage->id, $service->findActiveSegment($original)->garage_id);
        $this->assertSame(80000, $service->costOf($session)); // 20 kWh x Rp 4.000, the new garage's price
    }

    public function test_session_is_refused_for_inactive_rentals_and_non_electric_garages(): void
    {
        $car = $this->car();
        $service = app(ChargingService::class);
        $ended = $this->rentalOf($car, $this->electricGarage(), today()->subDays(5)->toDateString(), today()->toDateString());
        $future = $this->rentalOf($car, $this->electricGarage(), today()->addDays(2)->toDateString(), today()->addDays(4)->toDateString());
        $petrol = Rental::factory()->for($car->user)->for($car, 'vehicle')->for(Garage::factory()->mobil())
            ->between(today()->addDays(10)->toDateString(), today()->addDays(12)->toDateString())->create();
        $petrolToday = Rental::factory()->for(Garage::factory()->mobil())->create();

        foreach ([$ended, $future, $petrol, $petrolToday] as $rental) {
            try {
                $service->addSession($rental, 10, self::URL);
                $this->fail('Expected a 404');
            } catch (NotFoundHttpException) {
                $this->addToAssertionCount(1);
            }
        }

        $this->assertSame(0, ChargingTransaction::count());
    }

    public function test_invalid_charge_saves_nothing(): void
    {
        $rental = $this->rentalOf($this->car(60), $this->electricGarage());

        $this->expectException(ValidationException::class);

        try {
            app(ChargingService::class)->addSession($rental, 60, self::URL);
        } finally {
            $this->assertSame(0, ChargingTransaction::count());
        }
    }

    public function test_record_session_does_not_check_the_rules(): void
    {
        // Used when a rental is created: the rental service has already validated the input.
        $rental = $this->rentalOf($this->car(), $this->electricGarage());

        $session = app(ChargingService::class)->recordSession($rental, 10, self::URL);

        $this->assertDatabaseHas('charging_trx', ['id' => $session->id, 'rental_id' => $rental->id, 'payment_proof_url' => self::URL]);
    }

    public function test_find_active_segment(): void
    {
        $car = $this->car();
        $service = app(ChargingService::class);
        $active = $this->rentalOf($car, $this->electricGarage());
        $ended = Rental::factory()->between(today()->subDays(4)->toDateString(), today()->toDateString())->create();

        $this->assertTrue($service->findActiveSegment($active)->is($active));
        $this->assertNull($service->findActiveSegment($ended)); // free again on its end date
    }

    public function test_cost_of_is_computed_from_the_segment(): void
    {
        $car = $this->car(60);
        $rental = $this->rentalOf($car, $this->electricGarage(2500));
        $session = ChargingTransaction::factory()->create(['rental_id' => $rental->id, 'current_kwh' => 15]);

        $this->assertSame(112500, app(ChargingService::class)->costOf($session->fresh()));
        $this->assertArrayNotHasKey('cost', $session->fresh()->getAttributes()); // never stored
        $this->assertInstanceOf(User::class, $rental->user);
    }
}
