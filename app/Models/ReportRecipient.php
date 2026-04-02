<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReportRecipient extends Model
{
    protected $fillable = [
        'name', 'email', 'type', 'partner_id', 'is_active', 'schedule_day', 'schedule_time'
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function partner()
    {
        return $this->belongsTo(\App\Models\Partner::class, 'partner_id', 'partner_id');
    }

    public function logs()
    {
        return $this->hasMany(ReportLog::class, 'recipient_id');
    }

    public function lastLog()
    {
        return $this->hasOne(ReportLog::class, 'recipient_id')->latestOfMany();
    }
}
