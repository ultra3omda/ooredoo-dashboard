<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Country extends Model
{
    use HasFactory;

    protected $table = 'country';
    protected $primaryKey = 'country_id';
    public $timestamps = false;

    protected $fillable = [
        'country_name',
        'country_flag',
        '_active',
        'entry_by',
    ];

    public function abonnements()
    {
        return $this->hasMany(Abonnement::class, 'country_id', 'country_id');
    }

    public function clients()
    {
        return $this->hasMany(Client::class, 'country_id', 'country_id');
    }

    public function paymentMethods()
    {
        return $this->hasMany(CountryPaymentMethod::class, 'country_id', 'country_id');
    }
}