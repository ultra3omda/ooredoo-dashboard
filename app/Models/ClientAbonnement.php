<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClientAbonnement extends Model
{
    use HasFactory;

    protected $table = 'client_abonnement';
    protected $primaryKey = 'client_abonnement_id';
    public $timestamps = false;

    protected $fillable = [
        'client_id',
        'tarif_id',
        'country_payments_methods_id',
        'client_abonnement_creation',
        'client_abonnement_expiration',
        'created_at',
        'client_deleted_id',
        'subscription_type',
        'campaign_id',
    ];

    public function client()
    {
        return $this->belongsTo(Client::class, 'client_id', 'client_id');
    }

    public function tarif()
    {
        return $this->belongsTo(AbonnementTarif::class, 'tarif_id', 'abonnement_tarifs_id');
    }

    public function paymentMethod()
    {
        return $this->belongsTo(CountryPaymentMethod::class, 'country_payments_methods_id', 'country_payments_methods_id');
    }
}