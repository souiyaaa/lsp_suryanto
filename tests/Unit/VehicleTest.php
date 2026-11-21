<?php

namespace Tests\Unit;

use App\Enums\VehicleType;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VehicleTest extends TestCase
{
    use RefreshDatabase;

    public function test_casts_and_belongs_to_user(): void
    {
        $user = User::factory()->create();
        $vehicle = $user->vehicles()->create([
            'type' => 'mobil_listrik',
            'plate_number' => 'L 1234 AB',
            'brand_model' => 'Hyundai Ioniq 5',
            'max_battery_kwh' => 60,
        ])->fresh();

        $this->assertSame(VehicleType::MobilListrik, $vehicle->type);
        $this->assertSame('60.00', $vehicle->max_battery_kwh);
        $this->assertInstanceOf(BelongsTo::class, $vehicle->user());
        $this->assertTrue($vehicle->user->is($user));
    }

    public function test_one_user_can_own_many_vehicles(): void
    {
        $user = User::factory()->create();
        Vehicle::factory()->count(3)->for($user)->create();

        $this->assertCount(3, $user->vehicles);
    }

    public function test_two_sepeda_without_plate_can_both_be_saved(): void
    {
        $user = User::factory()->create();
        Vehicle::factory()->sepeda()->count(2)->for($user)->create();

        $this->assertSame(2, Vehicle::whereNull('plate_number')->count());
    }

    public function test_duplicate_plate_is_rejected_by_database_even_across_users(): void
    {
        Vehicle::factory()->create(['plate_number' => 'L 1234 AB']);

        $this->expectException(QueryException::class);
        Vehicle::factory()->create(['plate_number' => 'L 1234 AB']);
    }

    public function test_factory_states_per_type(): void
    {
        $sepeda = Vehicle::factory()->sepeda()->create();
        $motor = Vehicle::factory()->sepedaMotor()->create();
        $mobil = Vehicle::factory()->mobil()->create();
        $ev = Vehicle::factory()->mobilListrik()->create();

        $this->assertSame(VehicleType::Sepeda, $sepeda->type);
        $this->assertNull($sepeda->plate_number);
        $this->assertSame(VehicleType::SepedaMotor, $motor->type);
        $this->assertNotNull($motor->plate_number);
        $this->assertSame(VehicleType::Mobil, $mobil->type);
        $this->assertSame(VehicleType::MobilListrik, $ev->type);
        $this->assertNotNull($ev->max_battery_kwh);
    }

    public function test_max_battery_stays_null_unless_mobil_listrik(): void
    {
        foreach (['sepeda', 'sepedaMotor', 'mobil'] as $state) {
            $this->assertNull(Vehicle::factory()->{$state}()->create()->max_battery_kwh, $state);
        }
    }
}
