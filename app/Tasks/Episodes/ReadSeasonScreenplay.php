<?php

declare(strict_types=1);

namespace App\Tasks\Episodes;

use App\Data\Episodes\EpisodeScriptData;
use App\Models\CharacterRevision;
use App\Models\Episode;
use App\Models\Series;
use App\Repositories\CharacterRevisionRepository;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\ValidationException;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

readonly class ReadSeasonScreenplay
{
    public function __construct(private CharacterRevisionRepository $revisions) {}

    public function run(Episode $episode, Media $asset): EpisodeScriptData
    {
        if ($asset->model_type !== new Series()->getMorphClass() || (int) $asset->model_id !== $episode->series_id
            || $asset->collection_name !== Series::ASSETS || $asset->disk !== 'local' || $asset->getCustomProperty('kind') !== 'season') {
            throw ValidationException::withMessages(['season' => 'Выберите план этого сериала.']);
        }

        $bytes = File::get($asset->getPath());

        if (hash('sha256', $bytes) !== $asset->getCustomProperty('checksum')) {
            throw ValidationException::withMessages(['season' => 'Сохранённый план изменился.']);
        }

        $plan = json_decode($bytes, true);
        $script = is_array($plan) ? data_get($plan, 'episodes.' . ($episode->number - 1)) : null;

        if (! is_array($script) || ! is_array($script['cast'] ?? null) || ! is_array($script['scenes'] ?? null)) {
            throw ValidationException::withMessages(['season' => 'В плане отсутствует сценарий этой серии.']);
        }

        $cast = [];
        $available = $this->revisions->approvedForSeason($episode->series_id);

        foreach ($script['cast'] as $name) {
            $matches = $available->filter(static fn(CharacterRevision $version): bool => $version->character?->name === $name);

            if (! is_string($name) || $matches->count() !== 1) {
                throw ValidationException::withMessages(['season' => 'Нужен один утверждённый персонаж с именем «' . (is_string($name) ? $name : '?') . '».']);
            }

            $cast[$name] = $matches->firstOrFail();
        }

        $script['character_revision_ids'] = array_values(array_map(static fn(CharacterRevision $version): int => $version->id, $cast));

        foreach ($script['scenes'] as $sceneIndex => $scene) {
            if (! is_array($scene) || ! is_array($scene['shots'] ?? null)) {
                throw ValidationException::withMessages(['season' => 'Не удалось прочитать кадры сцены.']);
            }

            foreach ($scene['shots'] as $shotIndex => $shot) {
                if (! is_array($shot) || ! is_array($shot['dialogue'] ?? null)) {
                    throw ValidationException::withMessages(['season' => 'Не удалось прочитать реплики кадра.']);
                }

                foreach ($shot['dialogue'] as $index => $line) {
                    $speaker = is_array($line) ? $line['speaker'] ?? null : null;

                    if (! is_string($speaker) || ! isset($cast[$speaker])) {
                        throw ValidationException::withMessages(['season' => 'Говорящий отсутствует в составе эпизода.']);
                    }

                    $shot['dialogue'][$index] = [...$line, 'character_id' => $cast[$speaker]->character_id];
                }

                if (isset($shot['visible'])) {
                    if (! is_array($shot['visible']) || ! is_array($shot['direction'] ?? [])) {
                        throw ValidationException::withMessages(['season' => 'Проверьте состав кадра.']);
                    }

                    $visible = [];

                    foreach ($shot['visible'] as $name) {
                        if (! is_string($name) || ! isset($cast[$name])) {
                            throw ValidationException::withMessages(['season' => 'Видимый персонаж отсутствует в составе эпизода.']);
                        }

                        $visible[] = $cast[$name]->character_id;
                    }

                    $direction = $shot['direction'] ?? [];
                    $shot['direction'] = [...$direction, 'visible_character_ids' => $visible];
                }

                $scene['shots'][$shotIndex] = $shot;
            }

            $script['scenes'][$sceneIndex] = $scene;
        }

        return EpisodeScriptData::validateAndCreate($script);
    }
}
