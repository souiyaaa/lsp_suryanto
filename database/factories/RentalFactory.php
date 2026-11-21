<?php

namespace Database\Factories;

use App\Enums\RentalType;
use App\Models\Garage;
use App\Models\Rental;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Rental>
 */
class RentalFactory extends Factory
{
    /**
     * Default: an original harian rental of 3 days starting today.
     * The vehicle belongs to the same user.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            // Resolved after user_id, so the vehicle belongs to the same user.
            'vehicle_id' => fn (array $attributes) => Vehicle::factory()
                ->create(['user_id' => $attributes['user_id']])->id,
            'garage_id' => Garage::factory(),
            'parent_rental_id' => null,
            'rental_type' => RentalType::Harian,
            'start_date' => today()->toDateString(),
            'end_date' => today()->addDays(3)->toDateString(),
            'payment_proof_url' => 'https://res.cloudinary.com/demo/image/upload/proof.jpg',
        ];
    }

    public function between(string $start, string $end): static
    {
        return $this->state(fn () => ['start_date' => $start, 'end_date' => $end]);
    }

    public function bulanan(): static
    {
        return $this->state(fn () => ['rental_type' => RentalType::Bulanan]);
    }

    /**
     * An extension segment of $original: same user and vehicle, starts when the
     * original ends. Always points at the ORIGINAL (pass a different garage to
     * simulate a slot conflict).
     */
    public function extensionOf(Rental $original, int $days = 3): static
    {
        $rootId = $original->parent_rental_id ?? $original->id;

        return $this->state(fn () => [
            'user_id' => $original->user_id,
            'vehicle_id' => $original->vehicle_id,
            'garage_id' => $original->garage_id,
            'parent_rental_id' => $rootId,
            'start_date' => $original->end_date->toDateString(),
            'end_date' => $original->end_date->addDays($days)->toDateString(),
        ]);
    }
}
