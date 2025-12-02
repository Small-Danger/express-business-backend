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
        Schema::create('system_settings', function (Blueprint $table) {
            $table->id();
            // Clé unique du paramètre (ex: "mad_to_cfa_rate", "default_currency", etc.)
            $table->string('key')->unique();
            // Valeur du paramètre (stockée en string, convertie selon le type)
            $table->text('value');
            // Type de données du paramètre (decimal, integer, string, boolean)
            $table->string('type', 20)->default('string');
            // Description du paramètre pour comprendre son utilité
            $table->text('description')->nullable();
            // Indique si le paramètre est actif (peut être désactivé temporairement)
            $table->boolean('is_active')->default(true);
            // Utilisateur qui a modifié ce paramètre en dernier
            $table->foreignId('updated_by_user_id')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('system_settings');
    }
};
