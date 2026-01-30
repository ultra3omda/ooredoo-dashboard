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
        Schema::create('user_operators', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('operator_name'); // Nom de l'opérateur (ex: "S'abonner via Timwe")
            $table->boolean('is_primary')->default(false); // Opérateur principal de l'utilisateur
            $table->boolean('is_active')->default(true);
            $table->timestamp('assigned_at')->useCurrent();
            $table->unsignedBigInteger('assigned_by')->nullable(); // Qui a affecté cet opérateur
            $table->timestamps();
            
            // Un utilisateur ne peut avoir qu'une seule association par opérateur
            $table->unique(['user_id', 'operator_name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_operators');
    }
};