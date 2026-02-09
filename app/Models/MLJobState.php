<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class MLJobState extends Model
{
    protected $table = 'ml_job_state';
    protected $primaryKey = 'job_name';
    public $incrementing = false;
    protected $keyType = 'string';
    
    // Pas de created_at, seulement updated_at
    const CREATED_AT = null;
    const UPDATED_AT = 'updated_at';

    protected $fillable = [
        'job_name',
        'last_processed_id',
        'last_processed_at',
    ];

    protected $casts = [
        'last_processed_id' => 'integer',
        'last_processed_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Récupère ou crée un checkpoint pour un job donné.
     */
    public static function getCheckpoint(string $jobName): self
    {
        return self::firstOrCreate(
            ['job_name' => $jobName],
            [
                'last_processed_id' => 0,
                'last_processed_at' => null,
            ]
        );
    }

    /**
     * Met à jour le checkpoint avec le dernier ID traité.
     */
    public function updateCheckpoint(int $lastId, ?\DateTime $lastProcessedAt = null): void
    {
        $this->last_processed_id = $lastId;
        $this->last_processed_at = $lastProcessedAt ?? now();
        $this->save();
    }

    /**
     * Réinitialise le checkpoint à 0.
     */
    public function reset(): void
    {
        $this->last_processed_id = 0;
        $this->last_processed_at = null;
        $this->save();
    }
}
