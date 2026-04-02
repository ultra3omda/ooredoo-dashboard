<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('recipient_id');
            $table->enum('report_type', ['ceo', 'marketing', 'partner']);
            $table->enum('status', ['pending', 'sent', 'failed'])->default('pending');
            $table->date('period_start');
            $table->date('period_end');
            $table->text('ai_suggestions')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->foreign('recipient_id')->references('id')->on('report_recipients')->cascadeOnDelete();
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_logs');
    }
};
