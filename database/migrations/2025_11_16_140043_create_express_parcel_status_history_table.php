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
        Schema::create('express_parcel_status_histories', function (Blueprint $table) {
            // Identifiant unique de l'entrée d'historique
            $table->id();
            // Colis Express concerné par ce changement de statut
            $table->foreignId('express_parcel_id')
                  ->constrained('express_parcels')
                  ->cascadeOnDelete();
            // Ancien statut du colis (avant changement)
            $table->string('old_status', 30)->nullable();
            // Nouveau statut du colis (après changement)
            $table->string('new_status', 30);
            // Utilisateur qui a effectué le changement de statut
            $table->foreignId('changed_by_user_id')
                  ->constrained('users')
                  ->cascadeOnDelete();
            // Date/heure précise du changement de statut
            $table->dateTime('changed_at');
            // Commentaire ou justification du changement (optionnel)
            $table->text('comment')->nullable();
            // Dates de création et de mise à jour
            $table->timestamps();

            // Index pour retrouver rapidement l'historique d'un colis
            $table->index('express_parcel_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('express_parcel_status_histories');
    }
};
