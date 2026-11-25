<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
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

    'supabase' => [
        'url' => env('SUPABASE_URL'),
        'key' => env('SUPABASE_SECRET_KEY', env('SUPABASE_ANON_KEY', env('SUPABASE_PUBLISHABLE_KEY'))),
        'storage_key' => env('SUPABASE_SECRET_KEY', env('SUPABASE_SERVICE_ROLE_KEY')),
        'submissions_bucket' => env('SUPABASE_SUBMISSIONS_BUCKET', 'Submission'),
        'users_table' => env('SUPABASE_USERS_TABLE', 'users'),
    ],

    'google_sheets' => [
        'spreadsheet_id' => env('GOOGLE_SHEETS_SPREADSHEET_ID'),
        'worksheet' => env('GOOGLE_SHEETS_WORKSHEET', 'Penilaian'),
        'service_account_json_base64' => env('GOOGLE_SERVICE_ACCOUNT_JSON_BASE64'),
    ],

];
