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
        Schema::table('business_orders', function (Blueprint $table) {
            // Compte débité pour l'achat des produits de la commande
            $table->foreignId('purchase_account_id')
                  ->nullable()
                  ->after('currency')
                  ->constrained('accounts')
                  ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('business_orders', function (Blueprint $table) {
            $table->dropForeign(['purchase_account_id']);
            $table->dropColumn('purchase_account_id');
        });
    }
};

