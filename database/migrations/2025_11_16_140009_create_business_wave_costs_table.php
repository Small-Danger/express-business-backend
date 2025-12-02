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
        Schema::create('business_wave_costs', function (Blueprint $table) {
            // Identifiant unique du coût lié à la vague Business
            $table->id();
            // Vague Business concernée par ce coût
            $table->foreignId('business_wave_id')
                  ->constrained('business_waves')
                  ->cascadeOnDelete();
            // Type de coût (flight_ticket/customs/logistics/other)
            $table->string('type', 50);
            // Libellé du coût (ex: Billet avion voyageur X)
            $table->string('label');
            // Montant du coût
            $table->decimal('amount', 15, 2);
            // Devise utilisée pour ce coût
            $table->string('currency', 10)->default('CFA');
            // Notes ou détails supplémentaires sur le coût
            $table->text('notes')->nullable();
            // Dates de création et de mise à jour
            $table->timestamps();

            // Index pour les recherches par vague Business
            $table->index('business_wave_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('business_wave_costs');
    }
};
