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
        Schema::create('aggregator_offer_map', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('abonnement_id');
            $table->unsignedBigInteger('abonnement_tarifs_id')->nullable();
            $table->string('aggregator_offre_id');
            $table->string('aggregator_service_id')->nullable();
            $table->string('period_type')->nullable(); // daily / monthly / annual
            $table->timestamps();

            $table->index(['abonnement_id', 'aggregator_offre_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('aggregator_offer_map');
    }
};




