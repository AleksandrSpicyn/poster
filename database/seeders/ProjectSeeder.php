<?php

namespace Database\Seeders;

use App\Enums\ChannelType;
use App\Models\Channel;
use App\Models\Project;
use Illuminate\Database\Seeder;

class ProjectSeeder extends Seeder
{
    public function run(): void
    {
        $project = Project::firstOrCreate(
            ['name' => 'Midjourney Shorts'],
            ['prompt' => $this->prompt()],
        );

        Channel::firstOrCreate(
            [
                'project_id' => $project->id,
                'type' => ChannelType::YOUTUBE,
            ],
            [
                'name' => 'AI Fakes X',
                'client_id' => config('services.youtube.client_id'),
                'client_secret' => config('services.youtube.client_secret'),
            ],
        );

        Channel::firstOrCreate(
            [
                'project_id' => $project->id,
                'type' => ChannelType::INSTAGRAM,
            ],
            [
                'name' => 'AI Fakes X',
                'client_id' => config('services.instagram.client_id'),
                'client_secret' => config('services.instagram.client_secret'),
                'credentials' => [
                    'access_token' => config('services.instagram.token'),
                    'channel_id' => '17841402123695030',
                ],
            ],
        );
    }

    private function prompt(): string
    {
        return <<<TEXT
Ты креативный помощник, который ежедневно придумывает визуальные идеи для коротких видеороликов. Целевая аудитория — молодёжь 12–25 лет. Видео создаются на основе визуального запроса (prompt) для генерации изображения с помощью DALL·E.

Проект называется “Midjourney Shorts”. Каждый день публикуется один визуально насыщенный и забавный запрос.

Тематика: **Что, если животное оказалось в непривычной для себя стране?**

Примеры:
- Что, если медведь оказался в Таиланде?
- Что, если пингвин попал в Бразилию?
- Что, если коала очутилась в Исландии?
- Что, если верблюд оказался в Японии?

Требования к промптам:
- Всегда начинай с **“Что, если...”**
- Упоминай конкретное животное и страну
- Опиши сцену или окружение, в котором оно оказалось
- Не более 1–2 предложений
- Лёгкий, ироничный стиль; визуально насыщенный образ
TEXT;
    }
}
