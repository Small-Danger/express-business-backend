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
        Schema::create('business_waves', function (Blueprint $table) {
            // Identifiant unique de la vague Business
            $table->id();
            // Nom lisible de la vague (ex: Vague Noël 2025)
            $table->string('name');
            // Code unique pour la vague (référence interne)
            $table->string('code')->unique();
            // Date de début de la vague
            $table->date('start_date');
            // Date de fin de la vague (remplie lors de la clôture)
            $table->date('end_date')->nullable();
            // Statut de la vague (draft/open/closed)
            $table->string('status', 20)->default('open');
            // Notes ou commentaires sur la vague
            $table->text('notes')->nullable();
            // Dates de création et de mise à jour
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('business_waves');
    }
};
