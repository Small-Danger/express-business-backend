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
        Schema::table('accounts', function (Blueprint $table) {
            $table->string('account_number')->nullable()->after('name');
            $table->string('bank_name')->nullable()->after('account_number');
            $table->decimal('current_balance', 15, 2)->default(0)->after('initial_balance');
            $table->unsignedBigInteger('created_by_user_id')->nullable()->after('is_active');
            $table->unsignedBigInteger('updated_by_user_id')->nullable()->after('created_by_user_id');
            
            $table->foreign('created_by_user_id')->references('id')->on('users')->onDelete('set null');
            $table->foreign('updated_by_user_id')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->dropForeign(['created_by_user_id']);
            $table->dropForeign(['updated_by_user_id']);
            $table->dropColumn(['account_number', 'bank_name', 'current_balance', 'created_by_user_id', 'updated_by_user_id']);
        });
    }
};
