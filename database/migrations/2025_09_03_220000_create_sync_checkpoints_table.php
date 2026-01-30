<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('sync_checkpoints', function (Blueprint $table) {
            $table->id();
            $table->string('table_name')->unique();
            $table->unsignedBigInteger('last_id')->default(0);
            $table->timestamp('last_ts')->nullable();
            $table->string('status')->default('idle');
            $table->unsignedInteger('last_run_ms')->default(0);
            $table->text('error')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_checkpoints');
    }
};



