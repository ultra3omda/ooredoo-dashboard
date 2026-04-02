<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tables d'agrégation pour le diagnostic Timwe (comme timwe_daily_stats).
     * Données pré-calculées par jour : lecture rapide pour 365 jours, mise à jour incrémentale à chaque transaction.
     */
    public function up(): void
    {
        if (!Schema::hasTable('timwe_diagnostic_daily_summary')) {
            Schema::create('timwe_diagnostic_daily_summary', function (Blueprint $table) {
                $table->id();
                $table->date('stat_date')->unique();
                $table->unsignedInteger('total_transactions')->default(0);
                $table->unsignedInteger('total_billed')->default(0);
                $table->decimal('total_revenue_tnd', 15, 3)->default(0);
                $table->unsignedSmallInteger('delivery_codes_count')->default(0);
                $table->timestamp('calculated_at')->nullable();
                $table->timestamps();
                $table->index('stat_date');
            });
        }

        if (!Schema::hasTable('timwe_diagnostic_daily_phone')) {
            Schema::create('timwe_diagnostic_daily_phone', function (Blueprint $table) {
                $table->id();
                $table->date('stat_date');
                $table->string('client_telephone', 32);
                $table->unsignedBigInteger('client_id');
                $table->string('client_name', 255)->nullable();
                $table->date('subscription_date')->nullable();
                $table->unsignedInteger('total_attempts')->default(0);
                $table->unsignedInteger('delivered')->default(0);
                $table->unsignedInteger('no_balance')->default(0);
                $table->unsignedInteger('not_delivered')->default(0);
                $table->unsignedInteger('other')->default(0);
                $table->decimal('total_charged_tnd', 12, 3)->default(0);
                $table->timestamp('last_attempt_at')->nullable();
                $table->json('delivery_codes')->nullable();
                $table->timestamps();
                $table->unique(['stat_date', 'client_telephone']);
                $table->index(['stat_date']);
                $table->index(['client_telephone']);
            });
        }

        if (!Schema::hasTable('timwe_diagnostic_daily_delivery')) {
            Schema::create('timwe_diagnostic_daily_delivery', function (Blueprint $table) {
                $table->id();
                $table->date('stat_date');
                $table->string('delivery_code', 32);
                $table->unsignedInteger('count')->default(0);
                $table->decimal('total_charged_tnd', 12, 3)->default(0);
                $table->timestamps();
                $table->unique(['stat_date', 'delivery_code']);
                $table->index(['stat_date']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('timwe_diagnostic_daily_delivery');
        Schema::dropIfExists('timwe_diagnostic_daily_phone');
        Schema::dropIfExists('timwe_diagnostic_daily_summary');
    }
};
