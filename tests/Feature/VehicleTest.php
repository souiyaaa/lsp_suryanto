<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VehicleTest extends TestCase
{
    use RefreshDatabase;

    public function test_tc_15_01_vehicle_is_saved_for_the_logged_in_user_only(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $this->actingAs($user)->post('/vehicles', [
            'type' => 'mobil', 'plate_number' => ' b 1234 xyz ', 'brand_model' => 'Honda Brio', 'user_id' => $other->id,
        ])->assertRedirect(route('vehicles.index'));

        $vehicle = Vehicle::firstOrFail();
        $this->assertSame($user->id, $vehicle->user_id);
        $this->assertSame('B 1234 XYZ', $vehicle->plate_number);
        $this->actingAs($user)->get('/vehicles')->assertSee('Honda Brio');
        $this->actingAs($other)->get('/vehicles')->assertDontSee('Honda Brio');
    }

    public function test_tc_16_01_plate_is_required_except_for_sepeda(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/vehicles', ['type' => 'mobil', 'brand_model' => 'Honda Brio'])->assertSessionHasErrors('plate_number');
        $this->actingAs($user)->post('/vehicles', ['type' => 'sepeda_motor', 'brand_model' => 'Vario'])->assertSessionHasErrors('plate_number');
        $this->actingAs($user)->post('/vehicles', ['type' => 'sepeda', 'plate_number' => 'IGNORED', 'brand_model' => 'Polygon'])->assertSessionHasNoErrors();

        $this->assertNull(Vehicle::where('brand_model', 'Polygon')->first()->plate_number); // a sepeda never keeps a plate
    }

    public function test_tc_16_02_plate_must_be_unique(): void
    {
        $owner = User::factory()->create();
        Vehicle::factory()->for($owner)->create(['plate_number' => 'B 1234 XYZ']);

        $this->actingAs(User::factory()->create())
            ->post('/vehicles', ['type' => 'mobil', 'plate_number' => 'b 1234 xyz', 'brand_model' => 'Avanza'])
            ->assertSessionHasErrors('plate_number');

        $this->assertSame(1, Vehicle::count());
    }

    public function test_tc_16_03_several_sepeda_without_a_plate_are_allowed(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/vehicles', ['type' => 'sepeda', 'brand_model' => 'Polygon'])->assertSessionHasNoErrors();
        $this->actingAs($user)->post('/vehicles', ['type' => 'sepeda', 'brand_model' => 'United'])->assertSessionHasNoErrors();

        $this->assertSame(2, Vehicle::whereNull('plate_number')->count());
    }

    public function test_tc_17_01_battery_capacity_is_required_only_for_mobil_listrik(): void
    {
        $user = User::factory()->create();
        $electric = ['type' => 'mobil_listrik', 'plate_number' => 'B 1 EV', 'brand_model' => 'Ioniq 5'];

        $this->actingAs($user)->post('/vehicles', $electric)->assertSessionHasErrors('max_battery_kwh');
        $this->actingAs($user)->post('/vehicles', $electric + ['max_battery_kwh' => 0])->assertSessionHasErrors('max_battery_kwh');
        $this->actingAs($user)->post('/vehicles', $electric + ['max_battery_kwh' => 72.6])->assertSessionHasNoErrors();
        // Not stored for other types, even if sent.
        $this->actingAs($user)->post('/vehicles', ['type' => 'mobil', 'plate_number' => 'B 2 C', 'brand_model' => 'Brio', 'max_battery_kwh' => 50]);

        $this->assertEquals(72.6, Vehicle::where('plate_number', 'B 1 EV')->first()->max_battery_kwh);
        $this->assertNull(Vehicle::where('plate_number', 'B 2 C')->first()->max_battery_kwh);
    }

    public function test_tc_18_01_a_user_sees_only_their_own_vehicles_and_can_own_several(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        Vehicle::factory()->for($user)->count(2)->sequence(['brand_model' => 'Mine One'], ['brand_model' => 'Mine Two'])->create();
        Vehicle::factory()->for($other)->create(['brand_model' => 'Not Mine']);

        $this->actingAs($user)->get('/vehicles')->assertOk()
            ->assertSee('Mine One')->assertSee('Mine Two')->assertDontSee('Not Mine');
    }
}
