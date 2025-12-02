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
        Schema::create('clients', function (Blueprint $table) {
            // Identifiant unique du client
            $table->id();
            // Code interne optionnel pour le client (référence rapide)
            $table->string('code')->nullable()->unique();
            // Prénom du client
            $table->string('first_name');
            // Nom du client
            $table->string('last_name');
            // Numéro de téléphone principal du client
            $table->string('phone');
            // Numéro WhatsApp du client (optionnel, peut être différent du numéro principal)
            $table->string('whatsapp_phone')->nullable();
            // Adresse email du client (optionnelle)
            $table->string('email')->nullable();
            // Pays du client (ex: Maroc)
            $table->string('country');
            // Ville du client (ex: Casablanca)
            $table->string('city');
            // Adresse détaillée du client (optionnelle)
            $table->string('address')->nullable();
            // Indique si le client est utilisé dans le module Business
            $table->boolean('is_business_client')->default(true);
            // Indique si le client est utilisé dans le module Express
            $table->boolean('is_express_client')->default(true);
            // Informations complémentaires sur le client
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
        Schema::dropIfExists('clients');
    }
};
