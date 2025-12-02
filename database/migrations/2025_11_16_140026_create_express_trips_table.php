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
        Schema::create('express_trips', function (Blueprint $table) {
            // Identifiant unique du départ/retour Express
            $table->id();
            // Vague Express à laquelle ce trajet appartient
            $table->foreignId('express_wave_id')
                  ->constrained('express_waves')
                  ->cascadeOnDelete();
            // Nom du trajet (ex: Départ 17 Déc Maroc → Cameroun)
            $table->string('name');
            // Sens du trajet (A_to_B ou B_to_A)
            $table->string('direction', 10);
            // Pays de départ (ex: Maroc)
            $table->string('from_country');
            // Ville de départ (ex: Casablanca)
            $table->string('from_city');
            // Pays d'arrivée (ex: Cameroun)
            $table->string('to_country');
            // Ville d'arrivée (ex: Douala)
            $table->string('to_city');
            // Date prévue du départ/retour
            $table->date('planned_date');
            // Date/heure réelle du départ/retour
            $table->dateTime('actual_date')->nullable();
            // Nom du voyageur associé à ce trajet
            $table->string('traveler_name');
            // Statut du trajet (planned/in_transit/arrived/closed)
            $table->string('status', 20)->default('planned');
            // Notes supplémentaires sur le trajet
            $table->text('notes')->nullable();
            // Dates de création et de mise à jour
            $table->timestamps();

            // Index pour filtrer par vague et sens du trajet
            $table->index(['express_wave_id', 'direction']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('express_trips');
    }
};
