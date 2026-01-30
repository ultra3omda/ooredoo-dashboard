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
        Schema::table('users', function (Blueprint $table) {
            $table->enum('platform_type', ['club_privileges', 'timwe_ooredoo'])
                  ->default('club_privileges')
                  ->after('created_by')
                  ->comment('Type de plateforme: club_privileges pour Club Privilèges, timwe_ooredoo pour Timwe/Ooredoo');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('platform_type');
        });
    }
};
