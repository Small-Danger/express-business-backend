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
        Schema::table('express_trips', function (Blueprint $table) {
            // Date de fin du trajet (remplie automatiquement lors de la clôture)
            $table->date('end_date')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('express_trips', function (Blueprint $table) {
            $table->dropColumn('end_date');
        });
    }
};

