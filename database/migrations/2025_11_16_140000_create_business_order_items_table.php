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
        Schema::create('business_order_items', function (Blueprint $table) {
            // Identifiant unique de la ligne de commande
            $table->id();
            // Commande Business à laquelle cet article appartient
            $table->foreignId('business_order_id')
                  ->constrained('business_orders')
                  ->cascadeOnDelete();
            // Produit commandé par le client
            $table->foreignId('product_id')
                  ->constrained('products')
                  ->restrictOnDelete();
            // Quantité de ce produit dans la commande
            $table->integer('quantity');
            // Prix de vente unitaire du produit pour cette commande
            $table->decimal('unit_price', 15, 2);
            // Prix total de vente pour cette ligne (quantity * unit_price)
            $table->decimal('total_price', 15, 2);
            // Prix d'achat unitaire du produit au moment de la commande
            $table->decimal('purchase_price', 15, 2);
            // Coût total d'achat pour cette ligne (quantity * purchase_price)
            $table->decimal('purchase_total', 15, 2);
            // Marge brute en montant pour cette ligne (total_price - purchase_total)
            $table->decimal('margin_amount', 15, 2)->default(0);
            // Taux de marge pour cette ligne en pourcentage (ex: 30.00 pour 30 %)
            $table->decimal('margin_rate', 5, 2)->nullable();
            // Devise utilisée pour cette ligne (ex: CFA)
            $table->string('currency', 10)->default('CFA');
            // Indique si ce produit a été reçu dans le pays d'arrivée
            $table->boolean('is_received')->default(false);
            // Date/heure de réception de cet article
            $table->dateTime('received_at')->nullable();
            // Utilisateur qui a confirmé la réception de cet article
            $table->foreignId('received_by_user_id')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();
            // Dates de création et de mise à jour
            $table->timestamps();

            // Index pour faciliter les recherches par commande
            $table->index('business_order_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('business_order_items');
    }
};
