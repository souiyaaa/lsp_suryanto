<?php

namespace Database\Factories;

use App\Models\ChargingTransaction;
use App\Models\Rental;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChargingTransaction>
 */
class ChargingTransactionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'rental_id' => Rental::factory(),
            'current_kwh' => fake()->randomFloat(2, 5, 40),
            'payment_proof_url' => 'https://res.cloudinary.com/demo/image/upload/charging.jpg',
        ];
    }
}
