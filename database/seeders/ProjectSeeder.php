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

        $channel = Channel::query()->firstOrCreate(
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
        $project->channels()->syncWithoutDetaching([$channel->id]);

        $channel = Channel::query()->firstOrCreate(
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
        $project->channels()->syncWithoutDetaching([$channel->id]);

        $channel = Channel::query()->firstOrCreate(
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
        $project->channels()->syncWithoutDetaching([$channel->id]);
    }

    private function prompt(): string
    {
        return <<<'TEXT'
You create original, funny visual micro-stories for a short-form video channel. The audience enjoys expressive animals, inventive physical comedy, and satisfying surprises. Each story must be immediately understandable on a phone and worth watching to the end.

Core theme: What if an animal found itself in an unfamiliar country and had to improvise its way out of a small everyday problem?

Creative direction:
- Name a specific animal and country. Let a concrete local detail such as weather, terrain, architecture, or an everyday object actively affect the problem; the country must be more than a decorative label. Avoid national stereotypes and tourist-postcard scenes.
- Give the animal a simple, visible goal and a distinctive reaction. Open with a problem already in motion, escalate it through the animal's own actions, and end with a clever, unexpected payoff that resolves that same goal.
- Build warm, playful comedy around relatable frustrations: keeping something dry, retrieving a runaway object, crossing an awkward obstacle, or protecting a small comfort. These are starting points, not a fixed list to repeat.
- Make every action change the situation. Prefer a readable near miss, reversal, improvised solution, or surprising use of an established prop over idle posing, repetitive toy play, random spectacle, or an unrelated final gag.
- Keep one main animal, one location, and at most two relevant props. Use coherent anatomy and believable cause and effect. All stakes are harmless; no distress, cruelty, injury, humiliation, or dangerous imitable challenges.
- Vary the animal, setting, goal, obstacle, rhythm, and type of payoff across recent episodes. Do not recycle the same gag by swapping species or countries. Avoid brand names, copyrighted characters, topical references that quickly expire, and dependence on written signs.
- The story must work without speech or captions. Its action should naturally support lively instrumental background music, synchronized effects, and a clear musical accent at the payoff; never rely on narration to explain events.

Return one compact English story beginning with "What if...", in exactly three sentences: hook, escalation, payoff. Follow the supplied duration and output constraints. Describe visible events only; do not include production instructions or a soundtrack description in the story itself.
TEXT;
    }
}
