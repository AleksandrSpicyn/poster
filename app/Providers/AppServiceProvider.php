<?php

namespace App\Providers;

use App\Classes\Generators\AudioGenerator\AudioGenerator;
use App\Classes\Generators\AudioGenerator\SunoUnofficial\SunoUnofficial;
use App\Classes\Generators\ImageGenerator\ChatGpt\ChatGpt;
use App\Classes\Generators\ImageGenerator\ImageGenerator;
use App\Classes\Generators\PromptGenerator\ChatGpt\ChatGpt as ChatGptPromptGenerator;
use App\Classes\Generators\PromptGenerator\PromptGenerator;
use App\Classes\Generators\VideoGenerator\Runway\Runway;
use App\Classes\Generators\VideoGenerator\VideoGenerator;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
    }

    public function boot(): void
    {
        $this->app->bind(ImageGenerator::class, ChatGpt::class);
        $this->app->bind(PromptGenerator::class, ChatGptPromptGenerator::class);
        $this->app->bind(VideoGenerator::class, Runway::class);
        $this->app->bind(AudioGenerator::class, SunoUnofficial::class);
    }
}
