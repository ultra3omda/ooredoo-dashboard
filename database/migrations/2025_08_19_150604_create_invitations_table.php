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
        Schema::create('invitations', function (Blueprint $table) {
            $table->id();
            $table->string('email');
            $table->string('token')->unique(); // Token d'invitation unique
            $table->unsignedBigInteger('invited_by'); // Qui a envoyé l'invitation
            $table->unsignedBigInteger('role_id'); // Rôle assigné à l'invité
            $table->string('operator_name')->nullable(); // Opérateur assigné (null pour super admin)
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->enum('status', ['pending', 'accepted', 'expired', 'cancelled'])->default('pending');
            $table->timestamp('expires_at'); // Expiration de l'invitation
            $table->timestamp('accepted_at')->nullable();
            $table->json('additional_data')->nullable(); // Données supplémentaires (message, etc.)
            $table->timestamps();
            
            $table->index(['email', 'status']);
            $table->index(['token', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invitations');
    }
};