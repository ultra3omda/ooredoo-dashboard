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
        Schema::create('aggregator_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->string('msisdn');
            $table->string('subscription_id')->nullable();
            $table->string('offre_id')->nullable();
            $table->string('service_id')->nullable();
            $table->dateTime('subscription_date')->nullable();
            $table->dateTime('unsubscription_date')->nullable();
            $table->dateTime('expire_date')->nullable();
            $table->string('status')->nullable();
            $table->string('state')->nullable();
            $table->dateTime('first_successbilling')->nullable();
            $table->dateTime('last_successbilling')->nullable();
            $table->unsignedInteger('success_billing')->nullable();
            $table->dateTime('last_status_update')->nullable();
            $table->timestamps();

            $table->index(['msisdn']);
            $table->index(['subscription_date']);
            $table->index(['expire_date']);
            $table->index(['offre_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('aggregator_subscriptions');
    }
};




