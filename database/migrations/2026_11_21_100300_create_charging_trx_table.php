<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('charging_trx', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rental_id')->constrained();
            $table->decimal('current_kwh', 6, 2);
            $table->string('payment_proof_url');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('charging_trx');
    }
};
