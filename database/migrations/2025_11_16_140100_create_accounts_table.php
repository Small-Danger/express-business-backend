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
        Schema::create('accounts', function (Blueprint $table) {
            // Identifiant unique du compte
            $table->id();
            // Nom du compte (ex: "Orange Money 1", "Orange Money 2", "CIH Bank")
            $table->string('name');
            // Type de compte (orange_money / cih_bank)
            $table->string('type', 50);
            // Devise du compte (CFA / MAD)
            $table->string('currency', 10);
            // Solde initial du compte (pour référence historique)
            $table->decimal('initial_balance', 15, 2)->default(0);
            // Notes ou détails supplémentaires sur le compte
            $table->text('notes')->nullable();
            // Indique si le compte est actif ou non
            $table->boolean('is_active')->default(true);
            // Dates de création et de mise à jour
            $table->timestamps();

            // Index pour les recherches par type et devise
            $table->index(['type', 'currency']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('accounts');
    }
};

