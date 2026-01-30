<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AggregatorSubscription extends Model
{
    protected $table = 'aggregator_subscriptions';

    protected $fillable = [
        'msisdn',
        'subscription_id',
        'offre_id',
        'service_id',
        'subscription_date',
        'unsubscription_date',
        'expire_date',
        'status',
        'state',
        'first_successbilling',
        'last_successbilling',
        'success_billing',
        'last_status_update',
    ];
}




