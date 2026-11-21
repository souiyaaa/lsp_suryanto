<?php

namespace Database\Factories;

use App\Enums\VehicleType;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Vehicle>
 */
class VehicleFactory extends Factory
{
    /**
     * Default state is a plain mobil.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'type' => VehicleType::Mobil,
            'plate_number' => $this->plate(),
            'brand_model' => fake()->randomElement(['Toyota Avanza', 'Honda Brio', 'Suzuki Ertiga']),
            'max_battery_kwh' => null,
        ];
    }

    public function sepeda(): static
    {
        return $this->state(fn () => [
            'type' => VehicleType::Sepeda,
            'plate_number' => null,
            'brand_model' => fake()->randomElement(['Polygon Xtrada', 'United Miami', 'Pacific Revolution']),
            'max_battery_kwh' => null,
        ]);
    }

    public function sepedaMotor(): static
    {
        return $this->state(fn () => [
            'type' => VehicleType::SepedaMotor,
            'brand_model' => fake()->randomElement(['Honda Vario 160', 'Yamaha NMax', 'Honda Beat']),
            'max_battery_kwh' => null,
        ]);
    }

    public function mobil(): static
    {
        return $this->state(fn () => ['type' => VehicleType::Mobil, 'max_battery_kwh' => null]);
    }

    public function mobilListrik(): static
    {
        return $this->state(fn () => [
            'type' => VehicleType::MobilListrik,
            'brand_model' => fake()->randomElement(['Hyundai Ioniq 5', 'Wuling Air EV', 'BYD Atto 3']),
            'max_battery_kwh' => fake()->randomElement([40.00, 50.30, 60.00, 72.60]),
        ]);
    }

    private function plate(): string
    {
        return strtoupper(fake()->unique()->bothify('? ####? ??'));
    }
}
