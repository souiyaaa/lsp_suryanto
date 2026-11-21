<?php

namespace Database\Factories;

use App\Enums\GarageType;
use App\Models\Garage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Garage>
 */
class GarageFactory extends Factory
{
    /** Per-prefix counter so generated codes never collide within a run. */
    private static array $counters = [];

    /**
     * Default state is a Mobil (C) garage.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return $this->forType(GarageType::Mobil);
    }

    public function sepeda(): static
    {
        return $this->state(fn () => $this->forType(GarageType::Sepeda));
    }

    public function mobil(): static
    {
        return $this->state(fn () => $this->forType(GarageType::Mobil));
    }

    public function mobilListrik(): static
    {
        return $this->state(fn () => $this->forType(GarageType::MobilListrik));
    }

    /** @return array<string, mixed> */
    private function forType(GarageType $type): array
    {
        $prefix = $type->codePrefix();
        self::$counters[$prefix] = (self::$counters[$prefix] ?? 0) + 1;

        return [
            'code' => $prefix.str_pad((string) self::$counters[$prefix], Garage::CODE_DIGITS, '0', STR_PAD_LEFT),
            'type' => $type,
            'daily_price' => match ($type) {
                GarageType::Sepeda => 10000,
                GarageType::Mobil => 40000,
                GarageType::MobilListrik => 50000,
            },
            'charging_price_per_kwh' => $type->hasCharging() ? 2500 : null,
            'is_used' => false,
        ];
    }
}
