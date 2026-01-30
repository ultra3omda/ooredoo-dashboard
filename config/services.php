<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Ooredoo Webservice Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for the external webservice that provides dashboard data
    |
    */

    'webservice' => [
        'base_url' => env('WEBSERVICE_BASE_URL', 'http://localhost:8080'),
        'api_key' => env('WEBSERVICE_API_KEY', ''),
        'timeout' => env('WEBSERVICE_TIMEOUT', 30),
        'retry_attempts' => env('WEBSERVICE_RETRY_ATTEMPTS', 3),
        'retry_delay' => env('WEBSERVICE_RETRY_DELAY', 1000), // milliseconds
        'cache_ttl' => env('WEBSERVICE_CACHE_TTL', 3600), // seconds
    ],

    /*
    |--------------------------------------------------------------------------
    | Aggregator API
    |--------------------------------------------------------------------------
    */
    'aggregator' => [
        'base_url' => env('AGGREGATOR_BASE_URL', 'https://payment.eklectic.tn/API'),
        'token' => env('AGGREGATOR_TOKEN', ''),
        'timeout' => env('AGGREGATOR_TIMEOUT', 30),
    ],

];
