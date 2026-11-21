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
use Tests\Concerns\MocksPaymentProofUploader;
use Tests\TestCase;

class ChargingTest extends TestCase
{
    use MocksPaymentProofUploader, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-11-21'));
    }

    public function test_tc_34_01_extra_charging_session_on_an_active_electric_rental(): void
    {
        $this->fakeUploader();
        $user = User::factory()->create();
        $car = Vehicle::factory()->for($user)->mobilListrik()->create(['max_battery_kwh' => 60]);
        $garage = Garage::factory()->mobilListrik()->create();
        $rental = Rental::factory()->for($user)->for($car, 'vehicle')->for($garage)->create();

        $this->actingAs($user)->get(route('charging.create', $rental))->assertOk();

        $this->actingAs($user)->post(route('charging.store', $rental), ['current_kwh' => 20, 'payment_proof' => $this->proof()])
            ->assertRedirect(route('rentals.index'));
        $this->assertSame(1, $rental->chargingTransactions()->count());

        // Must stay below the vehicle's capacity (ChargingService).
        $this->actingAs($user)->post(route('charging.store', $rental), ['current_kwh' => 60, 'payment_proof' => $this->proof()])
            ->assertSessionHasErrors();
        $this->assertSame(1, $rental->chargingTransactions()->count());
    }

    public function test_tc_34_02_charging_preview_endpoint(): void
    {
        $user = User::factory()->create();
        $car = Vehicle::factory()->for($user)->mobilListrik()->create(['max_battery_kwh' => 60]);
        $rental = Rental::factory()->for($user)->for($car, 'vehicle')->for(Garage::factory()->mobilListrik())->create();

        $this->actingAs($user)->getJson("/api/charging-preview?rental_id={$rental->id}&current_kwh=15")
            ->assertOk()->assertJsonPath('data.cost', 'Rp 112.500');

        $this->actingAs(User::factory()->create())->getJson("/api/charging-preview?rental_id={$rental->id}&current_kwh=15")
            ->assertNotFound();
    }

    public function test_tc_35_01_charging_is_hidden_for_other_users_inactive_extension_and_non_electric_rentals(): void
    {
        $this->fakeUploader();
        $user = User::factory()->create();
        $car = Vehicle::factory()->for($user)->mobilListrik()->create(['max_battery_kwh' => 60]);
        $electric = Garage::factory()->mobilListrik()->create();
        $mine = Rental::factory()->for($user)->for($car, 'vehicle')->for($electric)->create();
        $extension = Rental::factory()->extensionOf($mine)->create();
        $inactive = Rental::factory()->for($user)->for($car, 'vehicle')->for($electric)
            ->between(today()->subDays(10)->toDateString(), today()->subDays(7)->toDateString())->create();
        $petrol = Rental::factory()->for($user)->for(Garage::factory()->mobil())->create();
        $strangers = Rental::factory()->for(Garage::factory()->mobilListrik())->create();

        foreach ([$extension, $inactive, $petrol, $strangers] as $rental) {
            $this->actingAs($user)->get(route('charging.create', $rental))->assertNotFound();
            $this->actingAs($user)->post(route('charging.store', $rental), ['current_kwh' => 10, 'payment_proof' => $this->proof()])->assertNotFound();
        }

        $this->assertSame(0, ChargingTransaction::count());
    }

    public function test_tc_35_02_my_rentals_offers_charging_only_on_active_electric_rentals(): void
    {
        $user = User::factory()->create();
        $car = Vehicle::factory()->for($user)->mobilListrik()->create(['max_battery_kwh' => 60]);
        $electric = Rental::factory()->for($user)->for($car, 'vehicle')->for(Garage::factory()->mobilListrik())->create();

        $this->actingAs($user)->get('/rentals')->assertSee(route('charging.create', $electric));

        $petrolUser = User::factory()->create();
        $petrol = Rental::factory()->for($petrolUser)->for(Garage::factory()->mobil())->create();
        $this->actingAs($petrolUser)->get('/rentals')->assertOk()
            ->assertDontSee('Bayar Pengecasan')->assertDontSee(route('charging.create', $petrol));
    }
}
