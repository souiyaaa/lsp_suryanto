<?php

use App\Enums\RentalType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rentals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->foreignId('vehicle_id')->constrained();
            $table->foreignId('garage_id')->constrained();
            $table->foreignId('parent_rental_id')->nullable()->constrained('rentals');
            $table->enum('rental_type', RentalType::values());
            $table->date('start_date'); // inclusive
            $table->date('end_date');   // exclusive
            $table->string('payment_proof_url');
            $table->timestamps();

            // Overlap checks
            $table->index(['garage_id', 'start_date', 'end_date']);
            $table->index(['vehicle_id', 'start_date', 'end_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rentals');
    }
};
