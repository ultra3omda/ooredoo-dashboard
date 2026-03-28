<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Partner extends Model
{
    protected $table = 'partner';
    protected $primaryKey = 'partner_id';
    public $timestamps = true;

    protected $fillable = [
        'partner_name', 'partner_mail', 'partner_category_id', 'partener_active'
    ];
}
