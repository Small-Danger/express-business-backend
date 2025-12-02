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
        Schema::table('express_parcels', function (Blueprint $table) {
            // Montant déjà payé par le client
            $table->decimal('total_paid', 15, 2)->default(0)->after('price_cfa');
            // Indique si le client doit encore de l'argent sur ce colis
            $table->boolean('has_debt')->default(false)->after('total_paid');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('express_parcels', function (Blueprint $table) {
            $table->dropColumn(['total_paid', 'has_debt']);
        });
    }
};

