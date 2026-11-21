<?php

namespace Tests\Feature;

use App\Exceptions\PaymentProofUploadException;
use App\Models\ChargingTransaction;
use App\Models\Garage;
use App\Models\Rental;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\PaymentProofUploader;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\Concerns\MocksPaymentProofUploader;
use Tests\TestCase;

class RentalTest extends TestCase
{
    use MocksPaymentProofUploader, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-11-21'));
    }

    public function test_tc_23_01_new_rental_is_saved_with_only_the_proof_url(): void
    {
        $this->fakeUploader();
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->for($user)->create();
        $garage = Garage::factory()->create();

        $this->actingAs($user)->post('/rentals', [
            'vehicle_id' => $vehicle->id, 'garage_id' => $garage->id, 'rental_type' => 'harian',
            'start_date' => today()->toDateString(), 'number_of_days' => 3, 'payment_proof' => $this->proof(),
        ])->assertRedirect(route('rentals.index'))->assertSessionHas('success');

        $rental = Rental::firstOrFail();
        $this->assertSame(self::PROOF_URL, $rental->payment_proof_url);
        $this->assertSame(3, $rental->numberOfDays());
        $this->assertTrue($garage->fresh()->is_used);
        $this->actingAs($user)->get('/rentals')->assertSee($garage->code);
    }

    public function test_tc_23_02_preview_endpoints_show_end_date_rent_and_total(): void
    {
        $user = User::factory()->create();
        $car = Vehicle::factory()->for($user)->mobilListrik()->create(['max_battery_kwh' => 60]);
        $petrol = Garage::factory()->mobil()->create(['daily_price' => 40000]);
        $electric = Garage::factory()->mobilListrik()->create(['daily_price' => 50000]);
        $this->actingAs($user);

        // A mobil listrik fits C and E garages, not S.
        $this->getJson("/api/garage-types?vehicle_id={$car->id}")->assertOk()
            ->assertJsonPath('success', true)->assertJsonCount(2, 'data');

        // Someone else's vehicle: not found.
        $this->getJson('/api/garage-types?vehicle_id='.Vehicle::factory()->create()->id)->assertNotFound();

        $this->getJson('/api/available-garages?'.http_build_query([
            'garage_type' => 'mobil', 'rental_type' => 'harian', 'start_date' => today()->toDateString(), 'number_of_days' => 3,
        ]))->assertOk()->assertJsonPath('data.0.code', $petrol->code)->assertJsonPath('data.0.daily_price_label', 'Rp 40.000');

        $this->getJson('/api/rental-preview?'.http_build_query([
            'vehicle_id' => $car->id, 'garage_id' => $electric->id, 'rental_type' => 'harian',
            'start_date' => today()->toDateString(), 'number_of_days' => 2, 'current_kwh' => 15,
        ]))->assertOk()->assertJsonPath('data.rent', 'Rp 100.000')->assertJsonPath('data.total', 'Rp 212.500');

        // Rule broken inside the service comes back as a JSON 422.
        $this->getJson('/api/rental-preview?'.http_build_query([
            'vehicle_id' => $car->id, 'garage_id' => $electric->id, 'rental_type' => 'harian',
            'start_date' => today()->toDateString(), 'number_of_days' => 31, 'current_kwh' => 15,
        ]))->assertStatus(422)->assertJsonPath('success', false);
    }

    public function test_tc_24_01_electric_rental_creates_the_first_charging_session(): void
    {
        $this->fakeUploader();
        $user = User::factory()->create();
        $car = Vehicle::factory()->for($user)->mobilListrik()->create(['max_battery_kwh' => 60]);
        $garage = Garage::factory()->mobilListrik()->create();

        $this->actingAs($user)->post('/rentals', [
            'vehicle_id' => $car->id, 'garage_id' => $garage->id, 'rental_type' => 'harian',
            'start_date' => today()->toDateString(), 'number_of_days' => 2, 'current_kwh' => 15, 'payment_proof' => $this->proof(),
        ])->assertSessionHasNoErrors();

        $session = ChargingTransaction::firstOrFail();
        $this->assertSame(self::PROOF_URL, $session->payment_proof_url);
        $this->assertEquals(15, $session->current_kwh);

        // 45 kWh x Rp 2.500 = Rp 112.500 (requirements §6 example).
        $this->actingAs($user)->get('/rentals')->assertSee('112.500')->assertSee('Bayar Pengecasan');
    }

    public function test_tc_24_02_electric_rental_requires_the_current_charge(): void
    {
        $this->fakeUploader();
        $user = User::factory()->create();
        $car = Vehicle::factory()->for($user)->mobilListrik()->create();
        $garage = Garage::factory()->mobilListrik()->create();

        $this->actingAs($user)->post('/rentals', [
            'vehicle_id' => $car->id, 'garage_id' => $garage->id, 'rental_type' => 'harian',
            'start_date' => today()->toDateString(), 'number_of_days' => 2, 'payment_proof' => $this->proof(),
        ])->assertSessionHasErrors('current_kwh');

        $this->assertSame(0, Rental::count());
    }

    public function test_tc_25_01_rental_rules_are_enforced_before_anything_is_saved(): void
    {
        $this->fakeUploader();
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->for($user)->create();
        $garage = Garage::factory()->create();
        $base = ['vehicle_id' => $vehicle->id, 'garage_id' => $garage->id, 'rental_type' => 'harian', 'start_date' => today()->toDateString()];

        // Not an image / too big / missing proof: rejected by the FormRequest.
        $this->actingAs($user)->post('/rentals', $base + ['number_of_days' => 2, 'payment_proof' => UploadedFile::fake()->create('a.pdf', 10, 'application/pdf')])->assertSessionHasErrors('payment_proof');
        $this->actingAs($user)->post('/rentals', $base + ['number_of_days' => 2, 'payment_proof' => UploadedFile::fake()->image('big.jpg')->size(2049)])->assertSessionHasErrors('payment_proof');
        $this->actingAs($user)->post('/rentals', $base + ['number_of_days' => 2])->assertSessionHasErrors('payment_proof');
        // More days than the month allows: rejected by RentalService.
        $this->actingAs($user)->post('/rentals', $base + ['number_of_days' => 31, 'payment_proof' => $this->proof()])->assertSessionHasErrors('number_of_days');
        // Past start date.
        $this->actingAs($user)->post('/rentals', ['start_date' => today()->subDay()->toDateString(), 'number_of_days' => 2, 'payment_proof' => $this->proof()] + $base)->assertSessionHasErrors('start_date');

        $this->assertSame(0, Rental::count());
    }

    public function test_tc_25_02_upload_failure_keeps_the_input_and_saves_nothing(): void
    {
        $this->mock(PaymentProofUploader::class)->shouldReceive('upload')->andThrow(new PaymentProofUploadException);
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->for($user)->create();
        $garage = Garage::factory()->create();

        $this->actingAs($user)->from('/rentals/create')->post('/rentals', [
            'vehicle_id' => $vehicle->id, 'garage_id' => $garage->id, 'rental_type' => 'harian',
            'start_date' => today()->toDateString(), 'number_of_days' => 3, 'payment_proof' => $this->proof(),
        ])->assertRedirect('/rentals/create')
            ->assertSessionHas('error', 'Gagal mengunggah bukti pembayaran, coba lagi')
            ->assertSessionHasInput('number_of_days', 3);

        $this->assertSame(0, Rental::count());
        $this->assertFalse($garage->fresh()->is_used);
    }

    public function test_tc_22_02_cannot_rent_with_someone_elses_vehicle(): void
    {
        $this->fakeUploader();
        $garage = Garage::factory()->create();
        $strangerVehicle = Vehicle::factory()->create();

        $this->actingAs(User::factory()->create())->post('/rentals', [
            'vehicle_id' => $strangerVehicle->id, 'garage_id' => $garage->id, 'rental_type' => 'harian',
            'start_date' => today()->toDateString(), 'number_of_days' => 2, 'payment_proof' => $this->proof(),
        ])->assertSessionHasErrors('vehicle_id');

        $this->assertSame(0, Rental::count());
    }

    public function test_tc_21_01_available_garages_are_free_ones_of_the_type_cheapest_first(): void
    {
        $user = User::factory()->create();
        $dear = Garage::factory()->create(['daily_price' => 50000]);
        $cheapest = Garage::factory()->create(['daily_price' => 30000]);
        $busy = Garage::factory()->create(['daily_price' => 20000]);
        $touching = Garage::factory()->create(['daily_price' => 40000]);
        Garage::factory()->sepeda()->create(['daily_price' => 10000]);
        Rental::factory()->for($busy)->between('2026-11-22', '2026-11-25')->create();     // overlaps [21, 24)
        Rental::factory()->for($touching)->between('2026-11-18', '2026-11-21')->create(); // ends on our start: free

        $this->actingAs($user)->getJson('/api/available-garages?'.http_build_query([
            'garage_type' => 'mobil', 'rental_type' => 'harian', 'start_date' => '2026-11-21', 'number_of_days' => 3,
        ]))->assertOk()->assertJsonPath('data.*.code', [$cheapest->code, $touching->code, $dear->code]);
    }

    public function test_tc_21_02_a_booked_garage_is_refused_even_if_posted_directly(): void
    {
        $this->fakeUploader();
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->for($user)->create();
        $garage = Garage::factory()->create();
        Rental::factory()->for($garage)->between('2026-11-22', '2026-11-26')->create();

        $this->actingAs($user)->post('/rentals', [
            'vehicle_id' => $vehicle->id, 'garage_id' => $garage->id, 'rental_type' => 'harian',
            'start_date' => '2026-11-21', 'number_of_days' => 3, 'payment_proof' => $this->proof(),
        ])->assertSessionHasErrors('garage_id');

        $this->assertSame(1, Rental::count());
    }

    public function test_tc_22_01_a_vehicle_cannot_be_in_two_overlapping_rentals(): void
    {
        $this->fakeUploader();
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->for($user)->create();
        Rental::factory()->for($user)->for($vehicle, 'vehicle')->between('2026-11-21', '2026-11-24')->create();
        $otherGarage = Garage::factory()->create();
        $post = fn (string $start) => $this->actingAs($user)->post('/rentals', [
            'vehicle_id' => $vehicle->id, 'garage_id' => $otherGarage->id, 'rental_type' => 'harian',
            'start_date' => $start, 'number_of_days' => 2, 'payment_proof' => $this->proof(),
        ]);

        $post('2026-11-23')->assertSessionHasErrors('vehicle_id');
        $this->assertSame(1, Rental::count());

        $post('2026-11-24')->assertSessionHasNoErrors(); // free again on the end date
        $this->assertSame(2, Rental::count());
    }

    public function test_tc_11_01_is_used_is_refreshed_when_the_rental_is_saved(): void
    {
        $this->fakeUploader();
        $user = User::factory()->create();
        $startsToday = Garage::factory()->create();
        $advance = Garage::factory()->create();

        foreach ([[$startsToday, '2026-11-21'], [$advance, '2026-12-10']] as [$garage, $start]) {
            $this->actingAs($user)->post('/rentals', [
                'vehicle_id' => Vehicle::factory()->for($user)->create()->id, 'garage_id' => $garage->id, 'rental_type' => 'harian',
                'start_date' => $start, 'number_of_days' => 2, 'payment_proof' => $this->proof(),
            ])->assertSessionHasNoErrors();
        }

        $this->assertTrue($startsToday->fresh()->is_used);
        $this->assertFalse($advance->fresh()->is_used);
    }
}
