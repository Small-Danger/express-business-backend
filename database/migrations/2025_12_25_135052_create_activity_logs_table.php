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
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            // Utilisateur qui a effectué l'action (nullable pour les actions système)
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            // Type d'action (login, logout, create, update, delete, etc.)
            $table->string('action_type', 50); // login, logout, create, update, delete, payment, transaction, etc.
            // Catégorie de l'action (auth, order, parcel, payment, transaction, etc.)
            $table->string('category', 50); // auth, order, parcel, payment, transaction, user, etc.
            // Type d'entité concernée (User, BusinessOrder, ExpressParcel, FinancialTransaction, etc.)
            $table->string('related_type', 50)->nullable();
            // ID de l'entité concernée
            $table->unsignedBigInteger('related_id')->nullable();
            // Description de l'action
            $table->text('description');
            // Données supplémentaires (JSON)
            $table->json('metadata')->nullable();
            // Adresse IP de l'utilisateur
            $table->string('ip_address', 45)->nullable();
            // User Agent (navigateur)
            $table->text('user_agent')->nullable();
            // Date de l'action
            $table->timestamp('created_at')->useCurrent();
            
            // Index pour améliorer les performances
            $table->index('user_id');
            $table->index('action_type');
            $table->index('category');
            $table->index(['related_type', 'related_id']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
