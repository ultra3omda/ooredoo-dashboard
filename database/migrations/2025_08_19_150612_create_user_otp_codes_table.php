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
        Schema::create('user_otp_codes', function (Blueprint $table) {
            $table->id();
            $table->string('email'); // Email de l'utilisateur
            $table->string('code', 6); // Code OTP à 6 chiffres
            $table->enum('type', ['login', 'invitation']); // Type d'OTP
            $table->string('invitation_token')->nullable(); // Token d'invitation associé (si applicable)
            $table->boolean('is_used')->default(false);
            $table->timestamp('expires_at'); // Expiration du code (ex: 10 minutes)
            $table->timestamp('used_at')->nullable();
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamps();
            
            $table->index(['email', 'code', 'is_used']);
            $table->index(['expires_at', 'is_used']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_otp_codes');
    }
};