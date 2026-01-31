<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Eklektik API Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration pour l'API Eklektik de paiement
    |
    */
    'api' => [
        'base_url' => env('EKLEKTIK_API_URL', 'https://payment.eklectic.tn/API'),
        'client_id' => env('EKLEKTIK_CLIENT_ID', '0a2e605d-88f6-11ec-9feb-fa163e3dd8b3'),
        'client_secret' => env('EKLEKTIK_CLIENT_SECRET', 'ee60bb148a0e468a5053f9db41008780'),
        'timeout' => env('EKLEKTIK_TIMEOUT', 30),
    ],

    /*
    |--------------------------------------------------------------------------
    | Eklektik Stats Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration pour l'API de statistiques Eklektik
    |
    */
    'stats' => [
        'base_url' => env('EKLEKTIK_STATS_URL', 'https://stats.eklectic.tn/getelements.php'),
        'timeout' => env('EKLEKTIK_STATS_TIMEOUT', 30),
        // Désactiver la vérification SSL si cURL erre 60 (certificat) en local / Windows
        'verify_ssl' => env('EKLEKTIK_VERIFY_SSL', true),
        
        // Accès par opérateur
        'credentials' => [
            'TT' => [
                'username' => env('EKLEKTIK_TT_USERNAME', 'ttclubpriv'),
                'password' => env('EKLEKTIK_TT_PASSWORD', 'tt22cp**'),
                'offers' => [11]
            ],
            'Orange' => [
                'username' => env('EKLEKTIK_ORANGE_USERNAME', 'clubprivorange'),
                'password' => env('EKLEKTIK_ORANGE_PASSWORD', 'club1234'),
                'offers' => [82, 87, 88, 141]
            ],
            'Taraji' => [
                'username' => env('EKLEKTIK_TARAJI_USERNAME', 'tarajipriv'),
                'password' => env('EKLEKTIK_TARAJI_PASSWORD', 'tt22cp**'),
                'offers' => [26]
            ]
        ],
        
        // Mapping des opérateurs (pour compatibilité)
        'operators' => [
            11 => 'TT',
            82 => 'Orange',
            87 => 'Orange',
            88 => 'Orange', 
            141 => 'Orange',
            26 => 'Taraji'
        ],
        
        // Configuration de synchronisation
        'sync' => [
            'enabled' => env('EKLEKTIK_SYNC_ENABLED', true),
            'daily_at' => env('EKLEKTIK_SYNC_DAILY_AT', '02:00'),
            'batch_size' => env('EKLEKTIK_SYNC_BATCH_SIZE', 100),
        ]
    ],

    /*
    |--------------------------------------------------------------------------
    | Eklektik Offer IDs
    |--------------------------------------------------------------------------
    |
    | Mapping des méthodes de paiement vers les offer_id Eklektik
    |
    */
    'offers' => [
        'S\'abonner via TT' => 11,
        'S\'abonner via Orange' => 82,
        'S\'abonner via Taraji' => 26,
        'S\'abonner via Timwe' => null, // Pas d'intégration Eklektik
        'Solde téléphonique' => null, // Détection dynamique
        'Solde Taraji mobile' => null, // Détection dynamique
    ],
];