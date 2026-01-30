<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Synchronisation Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration pour la synchronisation des données depuis l'API Club Privilèges
    |
    */

    'url' => env('SYNC_API_URL', 'https://api.clubprivileges.tn/sync'),
    'token' => env('SYNC_API_TOKEN'),
    'timeout' => env('SYNC_TIMEOUT', 30),
    'batch_size' => env('SYNC_BATCH_SIZE', 5000),
    
    'retry' => [
        'times' => env('SYNC_RETRY_TIMES', 3),
        'sleep_ms' => env('SYNC_RETRY_SLEEP_MS', 2000), // 2 secondes
    ],
    
    'request_delay_ms' => env('SYNC_REQUEST_DELAY_MS', 500), // 500ms entre requêtes
    
    'tables' => [
        'partner' => 'partner_id',
        'promotion' => 'promotion_id', 
        'client' => 'client_id',
        'client_abonnement' => 'client_abonnement_id',
        'promotion_pass_orders' => 'order_id',
        'promotion_pass_vendu' => 'vendu_id',
        'history' => 'history_id',
    ],
];