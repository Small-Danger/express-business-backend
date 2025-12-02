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
            // Informations de récupération (similaire à ExpressParcel)
            $table->string('pickup_receiver_name')->nullable();
            $table->string('pickup_receiver_phone')->nullable();
            $table->string('pickup_receiver_id_number')->nullable();
            $table->text('pickup_receiver_note')->nullable();
            $table->dateTime('picked_up_at')->nullable();
            $table->foreignId('picked_up_by_user_id')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('business_orders', function (Blueprint $table) {
            $table->dropForeign(['picked_up_by_user_id']);
            $table->dropColumn([
                'pickup_receiver_name',
                'pickup_receiver_phone',
                'pickup_receiver_id_number',
                'pickup_receiver_note',
                'picked_up_at',
                'picked_up_by_user_id',
            ]);
        });
    }
};

