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

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Broker Summary & Broker Flow API (stock.arjum.com)
    |--------------------------------------------------------------------------
    |
    | Kalau provider ganti URL/API key lagi di kemudian hari, cukup update
    | BROKER_API_BASE / BROKER_API_KEY di .env — tidak perlu sentuh kode
    | BrokerSummaryService atau BrokerSummaryController sama sekali.
    |
    | broker_flow_base_url dibiarkan bisa di-override terpisah lewat
    | BROKER_FLOW_API_BASE, jaga-jaga kalau endpoint flow ternyata beda
    | path/domain dari endpoint broker-summary.
    |
    */

    'broker_summary' => [
    'base_url' => env('BROKER_API_BASE', 'https://stock.arjum.com/api/broker-summary'),
    'api_key'  => env('BROKER_API_KEY'),
],


];
