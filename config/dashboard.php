<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Dashboard Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration pour les performances et limites du dashboard
    |
    */

    'performance' => [
        /*
        |--------------------------------------------------------------------------
        | Timeouts
        |--------------------------------------------------------------------------
        */
        'query_timeout' => env('DASHBOARD_QUERY_TIMEOUT', 30), // secondes
        'api_timeout' => env('DASHBOARD_API_TIMEOUT', 60), // secondes
        'cache_timeout' => env('DASHBOARD_CACHE_TIMEOUT', 120), // secondes
        
        /*
        |--------------------------------------------------------------------------
        | Limites de période
        |--------------------------------------------------------------------------
        */
        'max_period_days' => env('DASHBOARD_MAX_PERIOD_DAYS', 365),
        'long_period_threshold' => env('DASHBOARD_LONG_PERIOD_THRESHOLD', 90),
        'optimization_threshold' => env('DASHBOARD_OPTIMIZATION_THRESHOLD', 180),
        
        /*
        |--------------------------------------------------------------------------
        | Limites de résultats
        |--------------------------------------------------------------------------
        */
        'max_merchants' => env('DASHBOARD_MAX_MERCHANTS', 100),
        'max_transactions_per_day' => env('DASHBOARD_MAX_TRANSACTIONS_PER_DAY', 10000),
        'max_memory_mb' => env('DASHBOARD_MAX_MEMORY_MB', 512),
    ],

    'cache' => [
        /*
        |--------------------------------------------------------------------------
        | Configuration du cache
        |--------------------------------------------------------------------------
        */
        'default_ttl' => env('DASHBOARD_CACHE_TTL', 300), // 5 minutes
        'long_period_ttl' => env('DASHBOARD_LONG_CACHE_TTL', 1800), // 30 minutes
        'heavy_query_ttl' => env('DASHBOARD_HEAVY_CACHE_TTL', 3600), // 1 heure
        
        'prefix' => env('DASHBOARD_CACHE_PREFIX', 'dashboard_v2'),
        'enable_stale_cache' => env('DASHBOARD_ENABLE_STALE_CACHE', true),
        'stale_multiplier' => env('DASHBOARD_STALE_MULTIPLIER', 3),
    ],

    'database' => [
        /*
        |--------------------------------------------------------------------------
        | Configuration base de données
        |--------------------------------------------------------------------------
        */
        'chunk_size' => env('DASHBOARD_DB_CHUNK_SIZE', 1000),
        'max_joins' => env('DASHBOARD_MAX_JOINS', 5),
        'enable_query_log' => env('DASHBOARD_ENABLE_QUERY_LOG', false),
        
        /*
        |--------------------------------------------------------------------------
        | Index recommandés
        |--------------------------------------------------------------------------
        */
        'required_indexes' => [
            'history' => [
                'idx_history_time',
                'idx_history_time_client',
                'idx_history_promotion',
                'idx_history_time_promo'
            ],
            'client_abonnement' => [
                'idx_ca_creation',
                'idx_ca_creation_cmp',
                'idx_ca_expiration'
            ],
            'country_payments_methods' => [
                'idx_cpm_name'
            ]
        ]
    ],

    'monitoring' => [
        /*
        |--------------------------------------------------------------------------
        | Monitoring et alertes
        |--------------------------------------------------------------------------
        */
        'enable_performance_log' => env('DASHBOARD_ENABLE_PERF_LOG', true),
        'slow_query_threshold' => env('DASHBOARD_SLOW_QUERY_THRESHOLD', 5000), // ms
        'memory_alert_threshold' => env('DASHBOARD_MEMORY_ALERT_THRESHOLD', 80), // %
        
        'metrics' => [
            'track_execution_time' => true,
            'track_memory_usage' => true,
            'track_cache_hits' => true,
            'track_query_count' => true,
        ]
    ],

    'fallback' => [
        /*
        |--------------------------------------------------------------------------
        | Configuration du fallback
        |--------------------------------------------------------------------------
        */
        'enable_fallback' => env('DASHBOARD_ENABLE_FALLBACK', true),
        'fallback_cache_ttl' => env('DASHBOARD_FALLBACK_CACHE_TTL', 3600),
        'max_retry_attempts' => env('DASHBOARD_MAX_RETRY_ATTEMPTS', 3),
        'retry_delay_ms' => env('DASHBOARD_RETRY_DELAY_MS', 1000),
    ],

    'optimization' => [
        /*
        |--------------------------------------------------------------------------
        | Optimisations automatiques
        |--------------------------------------------------------------------------
        */
        'auto_optimize_long_periods' => env('DASHBOARD_AUTO_OPTIMIZE_LONG_PERIODS', true),
        'enable_query_optimization' => env('DASHBOARD_ENABLE_QUERY_OPTIMIZATION', true),
        'enable_result_pagination' => env('DASHBOARD_ENABLE_RESULT_PAGINATION', true),
        
        'granularity' => [
            'daily_threshold' => 30,    // jours
            'weekly_threshold' => 120,  // jours
            'monthly_threshold' => 365, // jours
        ]
    ],

    'api' => [
        /*
        |--------------------------------------------------------------------------
        | Configuration API
        |--------------------------------------------------------------------------
        */
        'rate_limit' => env('DASHBOARD_API_RATE_LIMIT', 60), // requêtes par minute
        'enable_compression' => env('DASHBOARD_ENABLE_COMPRESSION', true),
        'max_response_size_mb' => env('DASHBOARD_MAX_RESPONSE_SIZE_MB', 10),
        
        'endpoints' => [
            'dashboard_data' => '/api/dashboard/data',
            'kpis' => '/api/dashboard/kpis',
            'merchants' => '/api/dashboard/merchants',
            'health' => '/api/dashboard/health',
            'cache' => '/api/dashboard/cache'
        ]
    ]
];

