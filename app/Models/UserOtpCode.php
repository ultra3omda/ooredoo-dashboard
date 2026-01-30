<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserOtpCode extends Model
{
    use HasFactory;

    protected $fillable = [
        'email',
        'code',
        'type',
        'invitation_token',
        'is_used',
        'expires_at',
        'used_at',
        'ip_address',
        'user_agent'
    ];

    protected $casts = [
        'is_used' => 'boolean',
        'expires_at' => 'datetime',
        'used_at' => 'datetime'
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

    public function markAsUsed(): void
    {
        $this->update([
            'is_used' => true,
            'used_at' => now()
        ]);
    }

    public function isForInvitation(): bool
    {
        return $this->type === 'invitation' && !empty($this->invitation_token);
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

    public function scopeForType($query, string $type)
    {
        return $query->where('type', $type);
    }

    // Méthodes statiques
    public static function generateCode(): string
    {
        return str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    public static function createForLogin(string $email, string $ipAddress = null, string $userAgent = null): self
    {
        return self::create([
            'email' => $email,
            'code' => self::generateCode(),
            'type' => 'login',
            'expires_at' => now()->addMinutes(10),
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent
        ]);
    }

    public static function createForInvitation(string $email, string $invitationToken, string $ipAddress = null, string $userAgent = null): self
    {
        return self::create([
            'email' => $email,
            'code' => self::generateCode(),
            'type' => 'invitation',
            'invitation_token' => $invitationToken,
            'expires_at' => now()->addMinutes(15), // Plus de temps pour les invitations
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent
        ]);
    }
}