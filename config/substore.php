<?php

/**
 * Dashboard Sub-Stores — distributeurs Pluxee (colonne carte_recharge.stores).
 * Liste d’IDs séparés par des virgules, ex. : SUBSTORE_PLUXEE_DISTRIBUTOR_IDS=61
 */
return [
    'pluxee_distributor_store_ids' => array_values(array_filter(array_map(
        'intval',
        explode(',', (string) env('SUBSTORE_PLUXEE_DISTRIBUTOR_IDS', '61'))
    ))),
    /** Inclure les clients dont l’employeur est le distributeur (client.sub_store) même si carte_recharge.stores ne contient pas l’ID */
    'pluxee_fallback_employer_sub_store' => filter_var(
        env('SUBSTORE_PLUXEE_FALLBACK_EMPLOYER_SUB_STORE', true),
        FILTER_VALIDATE_BOOLEAN
    ),
    /** JSON_CONTAINS sur gros volumes peut provoquer des timeouts — activer seulement si stores est du JSON MySQL */
    'pluxee_match_json_stores' => filter_var(
        env('SUBSTORE_PLUXEE_MATCH_JSON_STORES', false),
        FILTER_VALIDATE_BOOLEAN
    ),
];
