<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('business_convoy_costs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_convoy_id')
                  ->constrained('business_convoys')
                  ->cascadeOnDelete();
            $table->string('type', 50); // Ex: 'flight_ticket', 'customs', 'transport', etc.
            $table->string('label');
            $table->decimal('amount', 15, 2);
            $table->string('currency', 10)->default('CFA');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('business_convoy_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('business_convoy_costs');
    }
};

