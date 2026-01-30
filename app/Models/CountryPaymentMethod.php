<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CountryPaymentMethod extends Model
{
    use HasFactory;

    protected $table = 'country_payments_methods';
    protected $primaryKey = 'country_payments_methods_id';
    public $timestamps = false;

    protected $fillable = [
        'country_id',
        'country_payments_methods_name',
        'country_payments_methods_desc',
        'country_payments_methods_module',
        'country_payments_methods_type',
        'country_payments_methods_icon',
        'country_payments_methods_gratuite',
        'entry_by',
        'app_publish',
    ];

    public function country()
    {
        return $this->belongsTo(Country::class, 'country_id', 'country_id');
    }

    public function abonnementTarifs()
    {
        return $this->hasMany(AbonnementTarif::class, 'country_payments_methods_id', 'country_payments_methods_id');
    }

    public function clientAbonnements()
    {
        return $this->hasMany(ClientAbonnement::class, 'country_payments_methods_id', 'country_payments_methods_id');
    }
}