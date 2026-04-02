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
        if (Schema::hasTable('eklektik_transactions_tracking')) {
            return;
        }
        Schema::create('eklektik_transactions_tracking', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('transaction_id')->index();
            $table->timestamp('processed_at')->useCurrent();
            $table->boolean('kpi_updated')->default(false);
            $table->string('processing_batch_id', 50)->nullable()->index();
            $table->json('processing_metadata')->nullable(); // Pour stocker des infos supplémentaires
            $table->timestamps();
            
            // Index pour les requêtes de performance
            $table->index(['processed_at', 'kpi_updated'], 'idx_processed_kpi');
            $table->index(['processing_batch_id', 'processed_at'], 'idx_batch_processed');
            
            // Contrainte de clé étrangère vers transactions_history
            $table->foreign('transaction_id')->references('transaction_history_id')->on('transactions_history');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('eklektik_transactions_tracking');
    }
};