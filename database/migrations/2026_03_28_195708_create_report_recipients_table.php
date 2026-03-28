<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_recipients', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->enum('type', ['ceo', 'marketing', 'partner'])->index();
            $table->unsignedBigInteger('partner_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('schedule_day')->default('monday');
            $table->string('schedule_time')->default('08:00');
            $table->timestamps();

            $table->foreign('partner_id')->references('partner_id')->on('partner')->nullOnDelete();
            $table->unique(['email', 'type', 'partner_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_recipients');
    }
};
