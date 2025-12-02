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
        Schema::create('express_waves', function (Blueprint $table) {
            // Identifiant unique de la vague Express
            $table->id();
            // Nom lisible de la vague Express (ex: Express Décembre 2025)
            $table->string('name');
            // Code unique pour la vague Express (référence interne)
            $table->string('code')->unique();
            // Date de début de la vague (ouverture)
            $table->date('start_date');
            // Date de fin de la vague (lors de la clôture)
            $table->date('end_date')->nullable();
            // Statut de la vague (open/closed)
            $table->string('status', 20)->default('open');
            // Notes ou commentaires sur la vague Express
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
        Schema::dropIfExists('express_waves');
    }
};
