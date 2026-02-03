<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Table d'agrégation lifetime par numéro pour le diagnostic Timwe.
     * Alimentée par l'observer (incrémental) ou par recalcul (TimweLifetimeAggregateService).
     */
    public function up(): void
    {
        if (Schema::hasTable('timwe_phone_lifetime_stats')) {
            return;
        }
        Schema::create('timwe_phone_lifetime_stats', function (Blueprint $table) {
            $table->string('client_telephone', 32)->primary();
            $table->unsignedInteger('lifetime_attempts')->default(0);
            $table->unsignedInteger('lifetime_delivered')->default(0);
            $table->unsignedInteger('lifetime_no_balance')->default(0);
            $table->unsignedInteger('lifetime_not_delivered')->default(0);
            $table->unsignedInteger('lifetime_other')->default(0);
            $table->decimal('lifetime_total_charged_tnd', 15, 3)->default(0);
            $table->timestamp('lifetime_last_attempt_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('timwe_phone_lifetime_stats');
    }
};
