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
        Schema::create('business_convoys', function (Blueprint $table) {
            // Identifiant unique du convoi Business
            $table->id();
            // Vague Business à laquelle ce convoi est rattaché
            $table->foreignId('business_wave_id')
                  ->constrained('business_waves')
                  ->cascadeOnDelete();
            // Nom du convoi (ex: Convoi Maroc → Cameroun #1)
            $table->string('name');
            // Nom du voyageur qui transporte les marchandises
            $table->string('traveler_name');
            // Pays de départ du convoi
            $table->string('from_country');
            // Ville de départ du convoi
            $table->string('from_city');
            // Pays d'arrivée du convoi
            $table->string('to_country');
            // Ville d'arrivée du convoi
            $table->string('to_city');
            // Date de départ prévue pour le convoi
            $table->date('planned_departure_date');
            // Date d'arrivée prévue pour le convoi
            $table->date('planned_arrival_date')->nullable();
            // Date/heure réelle de départ (remplie le jour J)
            $table->dateTime('actual_departure_date')->nullable();
            // Date/heure réelle d'arrivée
            $table->dateTime('actual_arrival_date')->nullable();
            // Statut du convoi (planned/in_transit/arrived/closed)
            $table->string('status', 20)->default('planned');
            // Notes supplémentaires sur le convoi
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
        Schema::dropIfExists('business_convoys');
    }
};
