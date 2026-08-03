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
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'safehaven' => [
        'external_api_url' => env('SAFEHAVEN_EXTERNAL_API_URL', ''),
        'external_api_key' => env('SAFEHAVEN_EXTERNAL_API_KEY', ''),
    ],

    'rootfi' => [
        'base_url' => env('ROOTFI_BASE_URL', 'https://api.rootfi.co'),
        'api_key' => env('ROOTFI_API_KEY', ''),
        'webhook_secret' => env('ROOTFI_WEBHOOK_SECRET', ''),
        'environment' => env('ROOTFI_ENVIRONMENT', 'live'),
        'master_account_number' => env('ROOTFI_MASTER_ACCOUNT_NUMBER', ''),
    ],

    // Checks run against a new signup before the verification email is sent.
    // These gate the outbound mail only - the account is created either way.
    'signup_risk' => [
        'check_mx'         => env('SIGNUP_CHECK_MX', true),
        'block_disposable' => env('SIGNUP_BLOCK_DISPOSABLE', true),
    ],

    // Reverse geolocation for logged IP addresses. The free ip-api tier needs no
    // account and is HTTP only; setting IP_API_KEY switches it to the paid HTTPS
    // endpoint. Results are cached per address in the ip_geolocations table.
    'ip_api' => [
        'enabled'  => env('IP_GEOLOCATION_ENABLED', true),
        'base_url' => env('IP_API_BASE_URL', 'http://ip-api.com/json'),
        'key'      => env('IP_API_KEY', ''),
    ],

    // Cloudflare Turnstile bot check on the public web signup forms.
    // Leave the keys unset to disable it entirely - the middleware is inert
    // without a secret, so shipping this cannot break signups.
    'turnstile' => [
        'site_key' => env('TURNSTILE_SITE_KEY', ''),
        'secret'   => env('TURNSTILE_SECRET', ''),
        'verify_url' => env('TURNSTILE_VERIFY_URL', 'https://challenges.cloudflare.com/turnstile/v0/siteverify'),
    ],

    'qoreid' => [
        'client_id' => env('QOREID_CLIENT_ID', ''),
        'secret_key' => env('QOREID_SECRET_KEY', ''),
        'webhook_secret' => env('QOREID_WEBHOOK_SECRET', ''),
        'sdk_url' => env('QOREID_SDK_URL', 'https://dashboard.qoreid.com/qoreid-sdk/qoreid.js'),
    ],

    // 'onesignal' => [
    //     'app_id' => env('ONESIGNAL_API_KEY'),
    //     'rest_api_key' => env('ONESIGNAL_REST_API_KEY'),
    //     'ONESIGNAL_APP_ID_PROVIDER' => env('ONESIGNAL_APP_ID_PROVIDER'),
    //     'ONESIGNAL_REST_API_KEY_PROVIDER' => env('ONESIGNAL_REST_API_KEY_PROVIDER'),
    // ],

];
