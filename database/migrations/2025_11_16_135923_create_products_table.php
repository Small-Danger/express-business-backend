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
        Schema::create('products', function (Blueprint $table) {
            // Identifiant unique du produit
            $table->id();
            // Nom du produit
            $table->string('name');
            // Référence interne unique (SKU) optionnelle
            $table->string('sku')->nullable()->unique();
            // Description détaillée du produit (optionnelle)
            $table->text('description')->nullable();
            // Prix d'achat du produit (coût fournisseur)
            $table->decimal('purchase_price', 15, 2);
            // Prix de vente standard du produit
            $table->decimal('sale_price', 15, 2);
            // Devise utilisée pour ce produit (ex: CFA, MAD)
            $table->string('currency', 10)->default('CFA');
            // Indique si le produit est encore actif ou non dans le catalogue
            $table->boolean('is_active')->default(true);
            // Dates de création et de mise à jour
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
