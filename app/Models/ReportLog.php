<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReportLog extends Model
{
    protected $fillable = [
        'recipient_id', 'report_type', 'status', 'period_start', 'period_end',
        'ai_suggestions', 'error_message', 'sent_at'
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'sent_at' => 'datetime',
    ];

    public function recipient()
    {
        return $this->belongsTo(ReportRecipient::class, 'recipient_id');
    }
}
