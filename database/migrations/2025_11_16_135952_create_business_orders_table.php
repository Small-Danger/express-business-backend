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
        Schema::create('business_orders', function (Blueprint $table) {
            // Identifiant unique de la commande Business
            $table->id();
            // Client qui a passé la commande
            $table->foreignId('client_id')
                  ->constrained('clients')
                  ->cascadeOnDelete();
            // Vague Business à laquelle la commande appartient
            $table->foreignId('business_wave_id')
                  ->constrained('business_waves')
                  ->cascadeOnDelete();
            // Convoi Business utilisé pour transporter cette commande (optionnel au début)
            $table->foreignId('business_convoy_id')
                  ->nullable()
                  ->constrained('business_convoys')
                  ->nullOnDelete();
            // Référence unique de la commande (BUS-ORD-XXXX)
            $table->string('reference')->unique();
            // Statut de la commande (pending/confirmed/in_transit/arrived/...)
            $table->string('status', 30)->default('pending');
            // Montant total facturé au client (somme des total_price des lignes)
            $table->decimal('total_amount', 15, 2)->default(0);
            // Montant déjà payé par le client
            $table->decimal('total_paid', 15, 2)->default(0);
            // Coût total d'achat des produits de la commande (somme des purchase_total des lignes)
            $table->decimal('total_purchase_cost', 15, 2)->default(0);
            // Marge brute totale en montant sur la commande
            $table->decimal('total_margin_amount', 15, 2)->default(0);
            // Taux de marge global sur la commande en pourcentage (ex: 25.50 pour 25,5 %)
            $table->decimal('margin_rate', 5, 2)->nullable();
            // Devise de la commande (ex: CFA)
            $table->string('currency', 10)->default('CFA');
            // Indique si le client doit encore de l'argent sur cette commande
            $table->boolean('has_debt')->default(false);
            // Indique si tous les articles de la commande ont été reçus
            $table->boolean('is_fully_received')->default(false);
            // Utilisateur qui a créé la commande (secrétaire/boss)
            $table->foreignId('created_by_user_id')
                  ->constrained('users')
                  ->cascadeOnDelete();
            // Dernier utilisateur ayant modifié la commande
            $table->foreignId('updated_by_user_id')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();
            // Dates de création et de mise à jour
            $table->timestamps();

            // Index pour faciliter la recherche par client et par vague
            $table->index(['client_id', 'business_wave_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('business_orders');
    }
};
