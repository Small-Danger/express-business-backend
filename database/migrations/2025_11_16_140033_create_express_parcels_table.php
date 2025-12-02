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
        Schema::create('express_parcels', function (Blueprint $table) {
            // Identifiant unique du colis Express
            $table->id();
            // Client expéditeur du colis (celui qui apporte le colis)
            $table->foreignId('client_id')
                  ->constrained('clients')
                  ->cascadeOnDelete();
            // Client receveur officiel dans le pays d'arrivée (optionnel)
            // Permet de sélectionner un receveur parmi les clients ou de le créer si besoin
            $table->foreignId('receiver_client_id')
                  ->nullable()
                  ->constrained('clients')
                  ->nullOnDelete();
            // Trajet Express (départ/retour) auquel le colis est rattaché
            $table->foreignId('express_trip_id')
                  ->constrained('express_trips')
                  ->cascadeOnDelete();
            // Référence unique du colis (numéro de suivi interne)
            $table->string('reference')->unique();
            // Description du colis (contenu, remarques)
            $table->text('description')->nullable();
            // Poids du colis en kilogrammes
            $table->decimal('weight_kg', 10, 3);
            // Prix à payer par le client en dirham (MAD)
            $table->decimal('price_mad', 15, 2);
            // Prix équivalent ou facturé en CFA
            $table->decimal('price_cfa', 15, 2);
            // Statut actuel du colis (registered/loaded/in_transit/arrived/...)
            $table->string('status', 30)->default('registered');

            // Informations sur la personne qui vient réellement récupérer le colis
            // (Peut être différente du receveur officiel : livreur, proche, etc.)
            // Nom de la personne venue récupérer le colis
            $table->string('pickup_receiver_name')->nullable();
            // Numéro de téléphone de cette personne
            $table->string('pickup_receiver_phone')->nullable();
            // Numéro de pièce d'identité (CNI, passeport, etc.)
            $table->string('pickup_receiver_id_number')->nullable();
            // Commentaires sur la récupération (ex: livreur mandaté, famille, etc.)
            $table->text('pickup_receiver_note')->nullable();
            // Date/heure à laquelle le colis a été remis
            $table->dateTime('picked_up_at')->nullable();
            // Utilisateur (secrétaire/boss) qui a validé la remise du colis
            $table->foreignId('picked_up_by_user_id')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();

            // Utilisateur qui a enregistré le colis (secrétaire/boss)
            $table->foreignId('created_by_user_id')
                  ->constrained('users')
                  ->cascadeOnDelete();
            // Dernier utilisateur ayant modifié les informations du colis
            $table->foreignId('updated_by_user_id')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();
            // Dates de création et de mise à jour
            $table->timestamps();

            // Index pour filtrer rapidement par client (expéditeur), receveur et trajet
            $table->index(['client_id', 'receiver_client_id', 'express_trip_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('express_parcels');
    }
};
