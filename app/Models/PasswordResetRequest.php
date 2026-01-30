<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;
use Illuminate\Support\Str;

class PasswordResetRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'email',
        'token',
        'type',
        'is_used',
        'expires_at',
        'used_at',
        'ip_address',
        'user_agent'
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'used_at' => 'datetime',
        'is_used' => 'boolean'
    ];

    // Méthodes utilitaires
    public function isExpired(): bool
    {
        return $this->expires_at < now();
    }

    public function isValid(): bool
    {
        return !$this->is_used && !$this->isExpired();
    }

    public function markAsUsed(string $ipAddress = null, string $userAgent = null): void
    {
        $this->update([
            'is_used' => true,
            'used_at' => now(),
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent
        ]);
    }

    // Scopes
    public function scopeValid($query)
    {
        return $query->where('is_used', false)
                    ->where('expires_at', '>', now());
    }

    public function scopeForEmail($query, string $email)
    {
        return $query->where('email', $email);
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }

    // Méthodes statiques
    public static function createForPasswordReset(string $email): self
    {
        // Invalider les anciennes demandes
        self::where('email', $email)
            ->where('type', 'password_reset')
            ->where('is_used', false)
            ->update(['is_used' => true]);

        return self::create([
            'email' => $email,
            'token' => Str::random(64),
            'type' => 'password_reset',
            'expires_at' => now()->addHours(1) // Expire dans 1 heure
        ]);
    }

    public static function createForFirstLogin(string $email): self
    {
        // Invalider les anciennes demandes
        self::where('email', $email)
            ->where('type', 'first_login')
            ->where('is_used', false)
            ->update(['is_used' => true]);

        return self::create([
            'email' => $email,
            'token' => Str::random(64),
            'type' => 'first_login',
            'expires_at' => now()->addDays(7) // Expire dans 7 jours
        ]);
    }
}
