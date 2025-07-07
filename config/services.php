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
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
        'webhook_url' => env('SLACK_WEBHOOK_URL'),
    ],

    // AI and Machine Learning Services
    'gemini' => [
        'api_key' => env('GEMINI_API_KEY'),
        'base_url' => env('GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta'),
        'default_model' => env('GEMINI_DEFAULT_MODEL', 'gemini-1.5-flash'),
        'embedding_model' => env('GEMINI_EMBEDDING_MODEL', 'text-embedding-004'),
        'max_tokens' => env('GEMINI_MAX_TOKENS', 1000),
        'temperature' => env('GEMINI_TEMPERATURE', 0.3),
        'timeout' => env('GEMINI_TIMEOUT', 30),
        'safety_settings' => [
            'HARM_CATEGORY_HARASSMENT' => 'BLOCK_MEDIUM_AND_ABOVE',
            'HARM_CATEGORY_HATE_SPEECH' => 'BLOCK_MEDIUM_AND_ABOVE',
            'HARM_CATEGORY_SEXUALLY_EXPLICIT' => 'BLOCK_MEDIUM_AND_ABOVE',
            'HARM_CATEGORY_DANGEROUS_CONTENT' => 'BLOCK_MEDIUM_AND_ABOVE',
        ],
    ],

    'anthropic' => [
        'api_key' => env('ANTHROPIC_API_KEY'),
        'base_url' => env('ANTHROPIC_BASE_URL', 'https://api.anthropic.com/v1'),
        'default_model' => env('ANTHROPIC_DEFAULT_MODEL', 'claude-3-sonnet-20240229'),
        'max_tokens' => env('ANTHROPIC_MAX_TOKENS', 1000),
        'temperature' => env('ANTHROPIC_TEMPERATURE', 0.3),
        'timeout' => env('ANTHROPIC_TIMEOUT', 30),
    ],

    // Vector Database Services
    'pinecone' => [
        'api_key' => env('PINECONE_API_KEY'),
        'environment' => env('PINECONE_ENVIRONMENT'),
        'index_name' => env('PINECONE_INDEX_NAME', 'support-center'),
        'dimension' => env('PINECONE_DIMENSION', 1536),
    ],

    'chroma' => [
        'host' => env('CHROMA_HOST', 'localhost'),
        'port' => env('CHROMA_PORT', 8000),
        'collection_name' => env('CHROMA_COLLECTION_NAME', 'knowledge_base'),
    ],

    // NEW: Global AI provider selector (gemini, anthropic, etc.)
    'ai_provider' => env('AI_PROVIDER', 'gemini'),

];
