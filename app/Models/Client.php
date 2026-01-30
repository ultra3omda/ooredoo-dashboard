<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Client extends Model
{
    use HasFactory;

    protected $table = 'client';
    protected $primaryKey = 'client_id';
    public $timestamps = false;

    protected $fillable = [
        'client_prenom',
        'client_nom',
        'client_telephone',
        'client_gender',
        'client_age',
        'client_active',
        'client_phone_id',
        'client_phone_os',
        'country_id',
        'client_store',
        'source',
        'client_auth',
        'client_email',
        'client_city',
        'sub_store',
        'sub_store_end_date',
        'tpv_preferences',
        'auth_provider',
        'auth_provider_id',
        'is_connect',
        'avatar',
        'external_id',
        'active_subscription',
        'campaign_id',
    ];

    public function country()
    {
        return $this->belongsTo(Country::class, 'country_id', 'country_id');
    }

    public function city()
    {
        return $this->belongsTo(City::class, 'client_city', 'city_id');
    }

    public function abonnements()
    {
        return $this->hasMany(ClientAbonnement::class, 'client_id', 'client_id');
    }
}