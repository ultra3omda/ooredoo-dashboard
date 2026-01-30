<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AggregatorOfferMap extends Model
{
    protected $table = 'aggregator_offer_map';

    protected $fillable = [
        'abonnement_id',
        'abonnement_tarifs_id',
        'aggregator_offre_id',
        'aggregator_service_id',
        'period_type',
    ];
}




