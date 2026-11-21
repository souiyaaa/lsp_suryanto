<?php

namespace Tests\Unit\Services;

use App\Enums\RentalType;
use App\Models\ChargingTransaction;
use App\Models\Garage;
use App\Models\Rental;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\RentalService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class RentalServiceTest extends TestCase
{
    use RefreshDatabase;

    private const URL = 'https://example.test/proof.jpg';

    private RentalService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-11-21'));
        $this->service = app(RentalService::class);
    }

    private function data(array $override = []): array
    {
        return $override + ['rental_type' => 'harian', 'start_date' => '2026-11-21', 'number_of_days' => 3];
    }

    private function assertRefused(string $field, callable $action): void
    {
        try {
            $action();
            $this->fail("Expected a validation error on {$field}");
        } catch (ValidationException $e) {
            $this->assertArrayHasKey($field, $e->errors());
        }
    }

    public function test_preview_daily(): void
    {
        $preview = $this->service->previewRental(Vehicle::factory()->create(), Garage::factory()->create(['daily_price' => 40000]), $this->data());

        $this->assertSame('2026-11-24', $preview['end_date']->toDateString());
        $this->assertSame(3, $preview['number_of_days']);
        $this->assertSame(120000, $preview['rent']);
        $this->assertNull($preview['charging']);
        $this->assertSame(120000, $preview['total']);
    }

    public function test_preview_monthly_uses_the_start_month_and_25_days_of_price(): void
    {
        $vehicle = Vehicle::factory()->create();
        $garage = Garage::factory()->create(['daily_price' => 40000]);

        $november = $this->service->previewRental($vehicle, $garage, $this->data(['rental_type' => 'bulanan', 'start_date' => '2026-11-21']));
        $february = $this->service->previewRental($vehicle, $garage, $this->data(['rental_type' => 'bulanan', 'start_date' => '2027-02-10']));

        $this->assertSame('2026-12-21', $november['end_date']->toDateString());
        $this->assertSame(30, $november['number_of_days']);
        $this->assertSame(1000000, $november['rent']);
        $this->assertSame('2027-03-10', $february['end_date']->toDateString());
        $this->assertSame(28, $february['number_of_days']);
        $this->assertSame(1000000, $february['rent']);
    }

    public function test_daily_limit_is_days_in_the_start_month_minus_one(): void
    {
        $vehicle = Vehicle::factory()->create();
        $garage = Garage::factory()->create();

        $this->assertSame(29, $this->service->previewRental($vehicle, $garage, $this->data(['number_of_days' => 29]))['number_of_days']);
        $this->assertSame(30, $this->service->previewRental($vehicle, $garage, $this->data(['start_date' => '2026-12-05', 'number_of_days' => 30]))['number_of_days']);

        $this->assertRefused('number_of_days', fn () => $this->service->previewRental($vehicle, $garage, $this->data(['number_of_days' => 30])));
        $this->assertRefused('number_of_days', fn () => $this->service->previewRental($vehicle, $garage, $this->data(['number_of_days' => 0])));
        $this->assertRefused('number_of_days', fn () => $this->service->previewRental($vehicle, $garage, $this->data(['start_date' => '2026-12-05', 'number_of_days' => 31])));
    }

    public function test_garage_type_must_fit_the_vehicle(): void
    {
        $this->assertRefused('garage_id', fn () => $this->service->previewRental(Vehicle::factory()->sepeda()->create(), Garage::factory()->mobil()->create(), $this->data()));
        $this->assertRefused('garage_id', fn () => $this->service->previewRental(Vehicle::factory()->mobil()->create(), Garage::factory()->mobilListrik()->create(), $this->data()));

        // Allowed: sepeda motor in S, mobil listrik in C (no charging) and in E.
        $this->assertNotEmpty($this->service->previewRental(Vehicle::factory()->sepedaMotor()->create(), Garage::factory()->sepeda()->create(), $this->data()));
        $this->assertNull($this->service->previewRental(Vehicle::factory()->mobilListrik()->create(), Garage::factory()->mobil()->create(), $this->data())['charging']);
    }

    public function test_electric_garage_needs_the_current_charge_and_adds_the_charging_cost(): void
    {
        $car = Vehicle::factory()->mobilListrik()->create(['max_battery_kwh' => 60]);
        $garage = Garage::factory()->mobilListrik()->create(['daily_price' => 50000, 'charging_price_per_kwh' => 2500]);

        $this->assertRefused('current_kwh', fn () => $this->service->previewRental($car, $garage, $this->data()));
        $this->assertRefused('current_kwh', fn () => $this->service->previewRental($car, $garage, $this->data(['current_kwh' => 60])));

        $preview = $this->service->previewRental($car, $garage, $this->data(['current_kwh' => 15]));

        $this->assertSame(150000, $preview['rent']);
        $this->assertSame(112500, $preview['charging']['cost']);
        $this->assertSame(262500, $preview['total']);
    }

    public function test_create_rental_saves_the_rental_and_marks_the_garage_used(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->for($user)->create();
        $garage = Garage::factory()->create();

        $rental = $this->service->createRental($user, $this->data(['vehicle_id' => $vehicle->id, 'garage_id' => $garage->id, 'start_date' => today()->toDateString()]), self::URL);

        $this->assertSame($user->id, $rental->user_id);
        $this->assertNull($rental->parent_rental_id);
        $this->assertSame(self::URL, $rental->payment_proof_url);
        $this->assertSame(RentalType::Harian, $rental->fresh()->rental_type);
        $this->assertTrue($garage->fresh()->is_used);
        $this->assertSame(0, ChargingTransaction::count());
    }

    public function test_advance_booking_does_not_mark_the_garage_used_yet(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->for($user)->create();
        $garage = Garage::factory()->create();

        $this->service->createRental($user, $this->data(['vehicle_id' => $vehicle->id, 'garage_id' => $garage->id, 'start_date' => today()->addMonths(3)->toDateString()]), self::URL);

        $this->assertFalse($garage->fresh()->is_used);
        $this->assertSame(1, Rental::count());
    }

    public function test_electric_rental_creates_the_first_charging_session_with_the_same_proof(): void
    {
        $user = User::factory()->create();
        $car = Vehicle::factory()->for($user)->mobilListrik()->create(['max_battery_kwh' => 60]);
        $garage = Garage::factory()->mobilListrik()->create();

        $rental = $this->service->createRental($user, $this->data(['vehicle_id' => $car->id, 'garage_id' => $garage->id, 'current_kwh' => 15]), self::URL);

        $session = $rental->chargingTransactions()->sole();
        $this->assertSame(self::URL, $session->payment_proof_url);
        $this->assertEquals(15, $session->current_kwh);
    }

    public function test_cannot_rent_with_someone_elses_vehicle(): void
    {
        $user = User::factory()->create();
        $stranger = Vehicle::factory()->create();
        $garage = Garage::factory()->create();

        $this->expectException(ModelNotFoundException::class);

        try {
            $this->service->createRental($user, $this->data(['vehicle_id' => $stranger->id, 'garage_id' => $garage->id]), self::URL);
        } finally {
            $this->assertSame(0, Rental::count());
        }
    }

    public function test_garage_and_vehicle_cannot_be_double_booked(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->for($user)->create();
        $otherVehicle = Vehicle::factory()->for($user)->create();
        $garage = Garage::factory()->create();
        $otherGarage = Garage::factory()->create();
        $this->service->createRental($user, $this->data(['vehicle_id' => $vehicle->id, 'garage_id' => $garage->id]), self::URL); // 21-24 Nov

        // Same garage, overlapping dates.
        $this->assertRefused('garage_id', fn () => $this->service->createRental($user, $this->data(['vehicle_id' => $otherVehicle->id, 'garage_id' => $garage->id, 'start_date' => '2026-11-23']), self::URL));
        // Same vehicle, overlapping dates, another garage.
        $this->assertRefused('vehicle_id', fn () => $this->service->createRental($user, $this->data(['vehicle_id' => $vehicle->id, 'garage_id' => $otherGarage->id, 'start_date' => '2026-11-22']), self::URL));
        $this->assertSame(1, Rental::count());

        // Touching ranges are fine: the garage and the vehicle are free again on 24 Nov.
        $this->service->createRental($user, $this->data(['vehicle_id' => $vehicle->id, 'garage_id' => $garage->id, 'start_date' => '2026-11-24']), self::URL);
        $this->assertSame(2, Rental::count());
    }

    public function test_a_refused_rental_saves_no_rows_at_all(): void
    {
        $user = User::factory()->create();
        $car = Vehicle::factory()->for($user)->mobilListrik()->create(['max_battery_kwh' => 60]);
        $garage = Garage::factory()->mobilListrik()->create();

        $this->assertRefused('current_kwh', fn () => $this->service->createRental($user, $this->data(['vehicle_id' => $car->id, 'garage_id' => $garage->id, 'current_kwh' => 70]), self::URL));
        $this->assertRefused('number_of_days', fn () => $this->service->createRental($user, $this->data(['vehicle_id' => $car->id, 'garage_id' => $garage->id, 'current_kwh' => 10, 'number_of_days' => 40]), self::URL));

        $this->assertSame(0, Rental::count());
        $this->assertSame(0, ChargingTransaction::count());
    }
}
