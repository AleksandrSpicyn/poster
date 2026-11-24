<?php

declare(strict_types=1);

use App\Classes\Generators\VideoGenerator\Hailuo\Hailuo;
use App\Classes\Generators\VideoGenerator\Runway\Runway;
use App\Classes\Generators\VideoGenerator\Seedance\Enums\Model;
use App\Classes\Generators\VideoGenerator\Seedance\Enums\Resolution;
use App\Classes\Generators\VideoGenerator\Seedance\Seedance;
use App\Classes\Media\Upscale\FalVideoUpscaler;
use App\Enums\UpscaleProvider;
use App\Enums\VideoProvider;

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

    'opus_clip' => [
        'api_url' => 'https://api.opus.pro/api/',
        'key' => env('OPUS_CLIP_API_KEY', ''),
    ],

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
    ],

    'upscale' => [
        'series_id' => (int) env('VIDEO_UPSCALE_SERIES_ID', 0),
        'budget_e8' => (int) env('VIDEO_UPSCALE_BUDGET_E8', 0),
        'provider' => env('VIDEO_UPSCALE_PROVIDER', UpscaleProvider::FAL->value),
        'drivers' => [UpscaleProvider::FAL->value => FalVideoUpscaler::class],
    ],

    'fal' => [
        'key' => env('FAL_KEY', ''),
    ],

    'video' => [
        'provider' => env('VIDEO_GENERATOR', VideoProvider::HAILUO->value),
        'fallback_provider' => env('VIDEO_FALLBACK_GENERATOR', VideoProvider::RUNWAY->value),
        'drivers' => [
            VideoProvider::HAILUO->value => Hailuo::class,
            VideoProvider::RUNWAY->value => Runway::class,
            VideoProvider::SEEDANCE->value => Seedance::class,
        ],
    ],

    'seedance' => [
        'api_key' => env('SEEDANCE_API_KEY', ''),
        'api_url' => env('SEEDANCE_API_URL', 'https://ark.ap-southeast.bytepluses.com/api/v3'),
        'model' => env('SEEDANCE_MODEL', Model::V2_0_MINI->value),
        'resolution' => env('SEEDANCE_RESOLUTION', Resolution::HD->value),
    ],

    'codex' => [
        'binary' => env('CODEX_BINARY', '/usr/local/bin/codex'),
        'home' => env('CODEX_HOME', storage_path('app/private/codex')),
        'timeout' => (int) env('CODEX_TIMEOUT', 300),
    ],

    'runway' => [
        'api_key' => env('RUNWAY_API_KEY', ''),
        'api_url' => env('RUNWAY_API_URL', 'https://api.dev.runwayml.com/v1'),
        'version' => env('RUNWAY_API_VERSION', '2024-11-06'),
    ],

    'hailuo' => [
        'api_key' => env('HAILUO_API_KEY', ''),
        'group_id' => env('HAILUO_GROUP_ID', ''),
        'api_url' => 'https://api.minimax.io/v1',
    ],

    'suno_unofficial' => [
        'api_key' => env('SUNO_UNOFFICIAL_API_KEY', ''),
        'api_url' => env('SUNO_UNOFFICIAL_API_URL', 'https://apibox.erweima.ai/api/v1'),
    ],

    'youtube' => [
        'api_url' => 'https://www.googleapis.com/youtube/v3',
        'analytics_url' => 'https://youtubeanalytics.googleapis.com/v2',
        'token_url' => 'https://oauth2.googleapis.com/token',
        'authorize_url' => 'https://accounts.google.com/o/oauth2/v2/auth',
        'data_api_key' => env('YOUTUBE_DATA_API_KEY', ''),
        'client_id' => env('YOUTUBE_CLIENT_ID'),
        'client_secret' => env('YOUTUBE_CLIENT_SECRET'),
    ],

    'telegram-bot-api' => [
        'token' => env('TELEGRAM_BOT_TOKEN', 'YOUR BOT TOKEN HERE'),
    ],

    'instagram' => [
        'graph_url' => 'https://graph.instagram.com',
        'token_url' => 'https://api.instagram.com/oauth/access_token',
        'authorize_url' => 'https://www.instagram.com/oauth/authorize',
        'client_id' => env('INSTAGRAM_CLIENT_ID'),
        'client_secret' => env('INSTAGRAM_CLIENT_SECRET'),
        'token' => env('INSTAGRAM_TOKEN'),
        'version' => 'v21.0',
    ],

    'admin' => [
        'email' => env('ADMIN_EMAIL', ''),
        'password' => env('ADMIN_PASSWORD', ''),
    ],

    'tiktok' => [
        'api_url' => 'https://open.tiktokapis.com/v2/',
        'authorize_url' => 'https://www.tiktok.com/v2/auth/authorize/',
        'direct_post' => env('TIKTOK_DIRECT_POST', false),
        'public_direct_post' => env('TIKTOK_PUBLIC_DIRECT_POST', false),
        'client_id' => env('TIKTOK_CLIENT_ID'),
        'client_secret' => env('TIKTOK_CLIENT_SECRET'),
    ],
];
