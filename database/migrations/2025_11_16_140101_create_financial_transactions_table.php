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
        Schema::create('financial_transactions', function (Blueprint $table) {
            // Identifiant unique de la transaction
            $table->id();
            // Compte concerné par la transaction
            $table->foreignId('account_id')
                  ->constrained('accounts')
                  ->cascadeOnDelete();
            // Type de transaction (debit / credit / transfer_out / transfer_in)
            $table->string('transaction_type', 20);
            // Montant de la transaction (dans la devise du compte)
            $table->decimal('amount', 15, 2);
            // Devise de la transaction (CFA / MAD)
            $table->string('currency', 10);
            // Référence unique de la transaction
            $table->string('reference')->unique();
            // Catégorie de transaction (order_purchase / order_payment / order_pickup_payment / 
            //                          convoy_cost / parcel_deposit / parcel_pickup_payment / 
            //                          wave_cost / trip_cost / transfer_conversion)
            $table->string('transaction_category', 50);
            // Type d'entité liée (BusinessOrder / BusinessConvoy / BusinessConvoyCost / 
            //                     ExpressParcel / ExpressWave / ExpressTrip / 
            //                     ExpressWaveCost / ExpressTripCost / Transfer / NULL)
            $table->string('related_type', 50)->nullable();
            // ID de l'entité liée (nullable pour les transfers)
            $table->unsignedBigInteger('related_id')->nullable();
            // Description de la transaction
            $table->text('description')->nullable();
            // Taux de change utilisé pour conversion (important pour les transfers)
            $table->decimal('exchange_rate_used', 10, 2)->nullable();
            // Référence du transfert jumelé (si transaction de transfert)
            $table->string('transfer_reference')->nullable();
            // Utilisateur qui a créé la transaction
            $table->foreignId('created_by_user_id')
                  ->constrained('users')
                  ->cascadeOnDelete();
            // Dates de création et de mise à jour
            $table->timestamps();

            // Index pour les recherches
            $table->index('account_id');
            $table->index('transaction_type');
            $table->index('transaction_category');
            $table->index(['related_type', 'related_id']);
            $table->index('transfer_reference');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('financial_transactions');
    }
};

