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
            $table->foreignId('role_id')->nullable()->constrained()->after('email'); // Rôle de l'utilisateur
            $table->string('first_name')->nullable()->after('name');
            $table->string('last_name')->nullable()->after('first_name');
            $table->string('phone')->nullable()->after('last_name');
            $table->enum('status', ['active', 'inactive', 'pending', 'suspended'])->default('pending')->after('phone');
            $table->timestamp('last_login_at')->nullable()->after('status');
            $table->string('last_login_ip')->nullable()->after('last_login_at');
            $table->json('preferences')->nullable()->after('last_login_ip'); // Préférences utilisateur
            $table->boolean('is_otp_enabled')->default(false)->after('preferences'); // Si l'utilisateur utilise OTP
            $table->timestamp('password_changed_at')->nullable()->after('is_otp_enabled');
            $table->unsignedBigInteger('created_by')->nullable()->after('password_changed_at'); // Qui a créé cet utilisateur
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['role_id']);
            $table->dropColumn([
                'role_id', 'first_name', 'last_name', 'phone', 'status',
                'last_login_at', 'last_login_ip', 'preferences', 'is_otp_enabled',
                'password_changed_at', 'created_by'
            ]);
        });
    }
};