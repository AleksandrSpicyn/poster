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
        Project::truncate();

        $prompt = <<<TEXT
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

        Project::factory()
            ->has(
                Channel::factory()->state([
                    'name' => 'Midjourney Shorts',
                    'type' => ChannelType::YOUTUBE,
                    'client_id' => config('services.youtube.client_id'),
                    'client_secret' => config('services.youtube.client_secret'),
                ]),
                'channels',
            )
            ->create([
                'name' => 'Midjourney Shorts',
                'prompt' => $prompt,
            ]);
    }
}
