<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AbonnementTarif extends Model
{
    use HasFactory;

    protected $table = 'abonnement_tarifs';
    protected $primaryKey = 'abonnement_tarifs_id';
    public $timestamps = false;

    protected $fillable = [
        'abonnement_id',
        'abonnement_tarifs_duration',
        'abonnement_tarifs_frequence',
        'abonnement_tarifs_prix',
        'abonnement_tarifs_cout',
        'abonnement_tarifs_priority',
        'country_payments_methods_id',
        'entry_by',
        'abonnement_tarifs_discount',
        'abonnement_tarifs_old_price',
    ];

    public function abonnement()
    {
        return $this->belongsTo(Abonnement::class, 'abonnement_id', 'abonnement_id');
    }

    public function paymentMethod()
    {
        return $this->belongsTo(CountryPaymentMethod::class, 'country_payments_methods_id', 'country_payments_methods_id');
    }
}