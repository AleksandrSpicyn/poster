<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\ChannelType;
use App\Models\Channel;
use App\Models\Project;
use Illuminate\Container\Attributes\Config;
use Illuminate\Database\Seeder;

class ProjectSeeder extends Seeder
{
    public function run(
        #[Config('services.youtube.client_id')] ?string $youtubeClientId,
        #[Config('services.youtube.client_secret')] ?string $youtubeClientSecret,
        #[Config('services.instagram.client_id')] ?string $instagramClientId,
        #[Config('services.instagram.client_secret')] ?string $instagramClientSecret,
        #[Config('services.instagram.token')] ?string $instagramToken,
        #[Config('services.tiktok.client_id')] ?string $tiktokClientId,
        #[Config('services.tiktok.client_secret')] ?string $tiktokClientSecret,
    ): void {
        $project = Project::query()->firstOrCreate(
            ['name' => 'Midjourney Shorts'],
            ['prompt' => $this->prompt()],
        );

        Channel::query()->firstOrCreate(
            [
                'project_id' => $project->id,
                'type' => ChannelType::YOUTUBE,
            ],
            [
                'name' => 'AI Fakes X',
                'client_id' => $youtubeClientId,
                'client_secret' => $youtubeClientSecret,
            ],
        );

        Channel::query()->firstOrCreate(
            [
                'project_id' => $project->id,
                'type' => ChannelType::INSTAGRAM,
            ],
            [
                'name' => 'AI Fakes X',
                'client_id' => $instagramClientId,
                'client_secret' => $instagramClientSecret,
                'credentials' => [
                    'access_token' => $instagramToken,
                    'channel_id' => '17841402123695030',
                ],
            ],
        );

        Channel::query()->firstOrCreate(
            [
                'project_id' => $project->id,
                'type' => ChannelType::TIKTOK,
            ],
            [
                'name' => 'AI Fakes X',
                'client_id' => $tiktokClientId,
                'client_secret' => $tiktokClientSecret,
            ],
        );
    }

    private function prompt(): string
    {
        return <<<'TEXT'
You are a creative assistant who comes up with visual ideas for short videos on a daily basis. The target audience is young people aged 12-25. Videos are created based on a visual prompt to generate an image using DALL E.

The project is called “Midjourney Shorts”. Every day, one visually rich and funny prompt is published.

Topic: **What if an animal ended up in an unfamiliar country?**

Examples:
- What if a bear ended up in Thailand?
- What if a penguin ended up in Brazil?
- What if a koala ended up in Iceland?
- What if a camel ended up in Japan?

Prompt requirements:
- Always start with **“What if...”**
- Mention a specific animal and country
- Describe the scene or environment it ended up in
- No more than 1-2 sentences
- Light, ironic style; visually rich image
TEXT;
    }
}
