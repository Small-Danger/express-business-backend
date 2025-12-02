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
        Schema::create('express_wave_costs', function (Blueprint $table) {
            // Identifiant unique du coût lié à la vague Express
            $table->id();
            // Vague Express concernée par ce coût
            $table->foreignId('express_wave_id')
                  ->constrained('express_waves')
                  ->cascadeOnDelete();
            // Type de coût (flight_ticket/customs/other)
            $table->string('type', 50);
            // Libellé du coût (ex: Billet aller Maroc → Cameroun)
            $table->string('label');
            // Montant du coût
            $table->decimal('amount', 15, 2);
            // Devise utilisée pour ce coût (ex: CFA)
            $table->string('currency', 10)->default('CFA');
            // Notes ou détails supplémentaires sur ce coût
            $table->text('notes')->nullable();
            // Dates de création et de mise à jour
            $table->timestamps();

            // Index pour les recherches par vague Express
            $table->index('express_wave_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('express_wave_costs');
    }
};
